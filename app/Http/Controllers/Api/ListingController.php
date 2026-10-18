<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ItemResource;
use App\Models\Booking;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Payout;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Owner side of listings. The app's 5-step flow maps to:
 *   1 photos   → POST /listings (draft) + POST /listings/{id}/photos
 *   2 details  → PUT  /listings/{id}
 *   3 pricing  → PUT  /listings/{id}
 *   4 calendar → PUT  /listings/{id}/availability
 *   5 pickup   → PUT  /listings/{id}
 *   preview    → GET  /listings/{id}   → POST /listings/{id}/submit
 * "Save draft" is just a PUT; nothing is public until it's approved.
 */
class ListingController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:draft,pending,active,paused,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $items = $request->user()->items()
            ->with(['photos', 'category'])
            ->withCount(['bookings as upcoming_bookings_count' => fn ($q) => $q->whereIn('status', [Booking::CONFIRMED, Booking::ACCEPTED])->whereDate('start_date', '>=', today())])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($data['per_page'] ?? 20);

        return ItemResource::collection($items)->additional(['meta' => ['counts' => $this->statusCounts($request->user())]]);
    }

    /** Header cards on "My listings": totals + pending/paid earnings. */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $payouts = Payout::where('owner_id', $user->id);

        return response()->json([
            'counts' => $this->statusCounts($user),
            'earnings' => [
                'pending' => round((float) (clone $payouts)->whereIn('status', [Payout::PENDING, Payout::AVAILABLE])->sum('amount'), 2),
                'pending_bookings' => (clone $payouts)->whereIn('status', [Payout::PENDING, Payout::AVAILABLE])->count(),
                'paid' => round((float) (clone $payouts)->where('status', Payout::PAID)->sum('amount'), 2),
                'paid_bookings' => (clone $payouts)->where('status', Payout::PAID)->count(),
            ],
            'pending_requests' => Booking::where('owner_id', $user->id)->where('status', Booking::REQUESTED)->count(),
        ]);
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $item->load(['photos', 'category', 'owner']);

        $upcoming = $item->bookings()->with(['renter'])
            ->whereIn('status', [Booking::REQUESTED, Booking::ACCEPTED, Booking::CONFIRMED, Booking::ACTIVE])
            ->orderBy('start_date')->limit(10)->get();

        return response()->json([
            'data' => new ItemResource($item),
            'blocked_dates' => $item->blockedDates()->whereDate('date', '>=', today())->orderBy('date')->pluck('date')->map->toDateString(),
            'upcoming_bookings' => BookingResource::collection($upcoming),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $item = new Item($data);
        $item->user_id = $request->user()->id;
        $item->status = Item::STATUS_DRAFT;
        $item->city ??= $request->user()->city;
        $item->province ??= $request->user()->province;
        $this->assertDayRange($item);
        $item->save();
        $item->refresh(); // pick up DB defaults (min_days, max_days, ...)

        if ($request->hasFile('photos')) {
            $this->addPhotos($request, $item);
        }

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))], 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $item->fill($request->validate($this->rules()));
        $this->assertDayRange($item);
        $item->save();

        // Material edits to a live listing don't need re-review here; tighten if your moderation policy requires.
        return response()->json(['data' => new ItemResource($item->fresh()->load(['photos', 'category']))]);
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);

        if ($item->bookings()->whereIn('status', Booking::BLOCKING)->exists()) {
            throw ValidationException::withMessages(['item' => 'This item has bookings in progress. Pause it instead.']);
        }

        $item->bookings()->where('status', Booking::REQUESTED)->update(['status' => Booking::DECLINED, 'declined_at' => now(), 'decline_reason' => 'Listing removed']);
        $item->delete();

        return response()->json(['message' => 'Listing deleted.']);
    }

    public function uploadPhotos(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $this->addPhotos($request, $item);

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    public function deletePhoto(Request $request, Item $item, ItemPhoto $photo): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        abort_unless($photo->item_id === $item->id, 404);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    /** Body: { "photo_ids": [5, 3, 9] } – first one becomes the cover. */
    public function reorderPhotos(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $ids = $request->validate(['photo_ids' => ['required', 'array'], 'photo_ids.*' => ['integer']])['photo_ids'];

        foreach ($ids as $i => $id) {
            $item->photos()->where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    /** Step 4: replace blocked dates within a range + pickup window + advance notice. */
    public function updateAvailability(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        $data = $request->validate([
            'blocked_dates' => ['present', 'array', 'max:400'],
            'blocked_dates.*' => ['date', 'after_or_equal:today'],
            'pickup_window_start' => ['nullable', 'date_format:H:i'],
            'pickup_window_end' => ['nullable', 'date_format:H:i', 'after:pickup_window_start'],
            'advance_notice_hours' => ['nullable', 'integer', 'min:0', 'max:336'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->blockedDates()->whereDate('date', '>=', today())->delete();
            $rows = collect($data['blocked_dates'])->map(fn ($d) => date('Y-m-d', strtotime($d)))->unique()
                ->map(fn ($d) => ['item_id' => $item->id, 'date' => $d])->values()->all();
            if ($rows) {
                $item->blockedDates()->insert($rows);
            }

            $item->update(array_filter([
                'pickup_window_start' => $data['pickup_window_start'] ?? null,
                'pickup_window_end' => $data['pickup_window_end'] ?? null,
                'advance_notice_hours' => $data['advance_notice_hours'] ?? null,
            ], fn ($v) => ! is_null($v)));
        });

        return $this->show($request, $item->fresh());
    }

    public function submit(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);

        if (! in_array($item->status, [Item::STATUS_DRAFT, Item::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be submitted.']);
        }
        if ($missing = $item->missingFieldsForSubmission()) {
            throw ValidationException::withMessages(['missing_fields' => 'Please complete: '.implode(', ', $missing)]);
        }

        $auto = config('neighbours.auto_approve_listings');
        $item->forceFill([
            'status' => $auto ? Item::STATUS_ACTIVE : Item::STATUS_PENDING,
            'submitted_at' => now(),
            'approved_at' => $auto ? now() : null,
            'rejection_reason' => null,
        ])->save();

        $this->notifications->send($request->user(), 'listing_submitted', 'updates',
            $auto ? 'Your listing is live' : 'Listing submitted',
            $auto ? "{$item->title} is now visible to neighbours." : "We'll review {$item->title} shortly and notify you once it's approved.",
            ['item_id' => $item->id]);

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    public function pause(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        if ($item->status !== Item::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Only active listings can be paused.']);
        }
        $item->forceFill(['status' => Item::STATUS_PAUSED])->save();

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    public function resume(Request $request, Item $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);
        if ($item->status !== Item::STATUS_PAUSED) {
            throw ValidationException::withMessages(['status' => 'Only paused listings can be resumed.']);
        }
        $item->forceFill(['status' => $item->approved_at ? Item::STATUS_ACTIVE : Item::STATUS_PENDING])->save();

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category']))]);
    }

    // ------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:80'],
            'model' => ['sometimes', 'nullable', 'string', 'max:80'],
            'condition' => ['sometimes', 'nullable', 'in:'.implode(',', config('neighbours.conditions'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'accessories' => ['sometimes', 'nullable', 'array', 'max:20'],
            'accessories.*' => ['string', 'max:80'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'rental_rules' => ['sometimes', 'nullable', 'string', 'max:1000'],

            'daily_rate' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:10000'],
            'min_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'max_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'security_deposit' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'cancellation_policy' => ['sometimes', 'in:'.implode(',', config('neighbours.cancellation_policies'))],

            'pickup_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'area' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'offers_delivery' => ['sometimes', 'boolean'],
            'delivery_radius_km' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0', 'max:1000'],

            'pickup_window_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'pickup_window_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'advance_notice_hours' => ['sometimes', 'integer', 'min:0', 'max:336'],
        ];
    }

    private function addPhotos(Request $request, Item $item): void
    {
        $max = config('neighbours.max_item_photos');
        $remaining = $max - $item->photos()->count();

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.max($remaining, 0)],
            'photos.*' => ['image', 'max:8192'],
        ], ['photos.max' => "You can upload up to {$max} photos."]);

        $order = (int) $item->photos()->max('sort_order');
        foreach ($this->storeUploads($request->file('photos'), "items/{$item->id}") as $path) {
            $item->photos()->create(['path' => $path, 'sort_order' => ++$order]);
        }
    }

    private function assertDayRange(Item $item): void
    {
        if ($item->min_days && $item->max_days && $item->max_days < $item->min_days) {
            throw ValidationException::withMessages(['max_days' => 'Maximum days must be at least the minimum.']);
        }
    }

    private function authorizeOwner(Request $request, Item $item): void
    {
        abort_unless($item->user_id === $request->user()->id, 403, 'This is not your listing.');
    }

    private function statusCounts(User $user): array
    {
        $counts = $user->items()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) ($counts['active'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'paused' => (int) ($counts['paused'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];
    }
}
