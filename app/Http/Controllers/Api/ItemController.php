<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Services\PricingService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Explore, search results, filters, item details, choose dates. */
class ItemController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string'], // id or slug; "all" ignored
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'delivery' => ['nullable', 'in:pickup,delivery'],
            'condition' => ['nullable', 'in:'.implode(',', config('neighbours.conditions'))],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:relevance,price_low,price_high,distance,rating,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$lat, $lng] = $this->searchCentre($request);

        $query = Item::query()->active()
            ->withDistance($lat, $lng, $data['radius_km'] ?? null)
            ->with(['photos', 'category', 'owner']);

        $this->withFavoriteFlag($query, $request);

        if (! empty($data['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $data['q']).'%';
            $query->where(fn (Builder $w) => $w->where('title', 'like', $term)
                ->orWhere('brand', 'like', $term)
                ->orWhere('model', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('tags', 'like', $term)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term)));
        }

        if (! empty($data['category']) && strtolower($data['category']) !== 'all') {
            $category = Category::where('id', $data['category'])->orWhere('slug', $data['category'])->orWhere('name', $data['category'])->first();
            $query->where('category_id', $category?->id ?? 0);
        }

        $query->when(isset($data['min_price']), fn ($q) => $q->where('daily_rate', '>=', $data['min_price']))
            ->when(isset($data['max_price']), fn ($q) => $q->where('daily_rate', '<=', $data['max_price']))
            ->when(($data['delivery'] ?? null) === 'delivery', fn ($q) => $q->where('offers_delivery', true))
            ->when(! empty($data['condition']), fn ($q) => $q->where('condition', $data['condition']))
            ->when(! empty($data['owner_id']), fn ($q) => $q->where('user_id', $data['owner_id']));

        if (! empty($data['start_date'])) {
            $query->availableBetween(Carbon::parse($data['start_date']), Carbon::parse($data['end_date']));
        }

        match ($data['sort'] ?? 'relevance') {
            'price_low' => $query->orderBy('daily_rate'),
            'price_high' => $query->orderByDesc('daily_rate'),
            'distance' => $query->orderBy('distance_km'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
            'newest' => $query->latest('approved_at'),
            default => $this->orderByRelevance($query, $data['q'] ?? null),
        };

        $items = $query->paginate($data['per_page'] ?? 20)->withQueryString();

        return ItemResource::collection($items)->additional([
            'meta' => ['search_centre' => ['lat' => $lat, 'lng' => $lng]],
        ]);
    }

    /** "Popular near you" on Explore. */
    public function popular(Request $request)
    {
        [$lat, $lng] = $this->searchCentre($request);

        $query = Item::query()->active()
            ->withDistance($lat, $lng, (float) $request->input('radius_km', 25))
            ->with(['photos', 'category', 'owner'])
            ->when($request->filled('category') && $request->input('category') !== 'all',
                fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->input('category'))->orWhere('id', $request->input('category'))))
            ->orderByRaw('(rating_avg * LEAST(reviews_count, 50)) DESC')
            ->orderBy('distance_km')
            ->limit(min((int) $request->input('limit', 10), 30));

        $this->withFavoriteFlag($query, $request);

        return ItemResource::collection($query->get());
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $item->user_id;

        if ($item->status !== Item::STATUS_ACTIVE && ! $isOwner && ! $viewer?->is_admin) {
            abort(404);
        }

        [$lat, $lng] = $this->searchCentre($request);
        $query = Item::whereKey($item->id)->withDistance($lat, $lng)->with(['photos', 'category', 'owner']);
        $this->withFavoriteFlag($query, $request);
        $item = $query->firstOrFail();

        if (! $isOwner) {
            Item::whereKey($item->id)->increment('views_count');
        }

        $reviews = $item->reviews()->with('reviewer')->latest()->limit(5)->get();

        return response()->json([
            'data' => new ItemResource($item),
            'reviews' => ReviewResource::collection($reviews),
        ]);
    }

    /** Calendar data for "Choose rental dates". */
    public function availability(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $from->copy()->addMonths(3)->endOfMonth();

        $blocked = $item->blockedDates()->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map->toDateString();

        $booked = collect();
        $item->bookings()->overlapping($from, $to)->get(['start_date', 'end_date'])->each(function (Booking $b) use (&$booked) {
            foreach (CarbonPeriod::create($b->start_date, $b->end_date) as $day) {
                $booked->push($day->toDateString());
            }
        });

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'blocked_dates' => $blocked->values(),
            'booked_dates' => $booked->unique()->sort()->values(),
            'unavailable_dates' => $blocked->merge($booked)->unique()->sort()->values(),
            'min_days' => $item->min_days,
            'max_days' => $item->max_days,
            'advance_notice_hours' => $item->advance_notice_hours,
            'pickup_window_start' => $item->pickup_window_start ? substr($item->pickup_window_start, 0, 5) : null,
            'pickup_window_end' => $item->pickup_window_end ? substr($item->pickup_window_end, 0, 5) : null,
        ]);
    }

    /** Live price breakdown while picking dates ("3 days • C$60"). */
    public function quote(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'delivery' => ['nullable', 'boolean'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $quote = $this->pricing->quote($item, $start, $end, (bool) ($data['delivery'] ?? false));

        $problems = [];
        if ($quote['days'] < $item->min_days) {
            $problems[] = "Minimum rental is {$item->min_days} day(s).";
        }
        if ($quote['days'] > $item->max_days) {
            $problems[] = "Maximum rental is {$item->max_days} day(s).";
        }
        if (! $item->isAvailable($start, $end)) {
            $problems[] = 'Some of these dates are unavailable.';
        }

        return response()->json(['data' => $quote, 'available' => empty($problems), 'problems' => $problems]);
    }

    private function searchCentre(Request $request): array
    {
        if ($request->filled(['lat', 'lng'])) {
            return [(float) $request->input('lat'), (float) $request->input('lng')];
        }
        $user = $request->user();
        if ($user?->latitude && $user?->longitude) {
            return [$user->latitude, $user->longitude];
        }
        $d = config('neighbours.default_location');

        return [$d['lat'], $d['lng']];
    }

    private function withFavoriteFlag(Builder $query, Request $request): void
    {
        if ($user = $request->user()) {
            $query->withExists(['favoritedBy as is_favorite' => fn ($q) => $q->where('users.id', $user->id)]);
        }
    }

    private function orderByRelevance(Builder $query, ?string $q): void
    {
        if ($q) {
            $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END', [$q.'%', '%'.$q.'%']);
        }
        $query->orderBy('distance_km')->orderByDesc('rating_avg');
    }
}
