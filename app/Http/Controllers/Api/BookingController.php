<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ExtensionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\BookingExtension;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Payout;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const RELATIONS = ['item.photos', 'item.category', 'renter', 'owner', 'extensions', 'conditionChecks', 'reviews', 'conversation'];

    public function __construct(private BookingService $bookings, private PricingService $pricing) {}

    /**
     * My Rentals. ?role=renter ("Renting") | owner ("My items" / incoming requests)
     * ?status=requested,accepted,...  or ?group=upcoming|active|past|requests
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['nullable', 'in:renter,owner,all'],
            'status' => ['nullable', 'string'],
            'group' => ['nullable', 'in:upcoming,active,past,requests'],
            'item_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $role = $data['role'] ?? 'renter';

        $query = Booking::with(['item.photos', 'item.category', 'renter', 'owner'])
            ->when($role === 'renter', fn ($q) => $q->where('renter_id', $user->id))
            ->when($role === 'owner', fn ($q) => $q->where('owner_id', $user->id))
            ->when($role === 'all', fn ($q) => $q->forUser($user))
            ->when($data['item_id'] ?? null, fn ($q, $id) => $q->where('item_id', $id))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->whereIn('status', explode(',', $s)));

        match ($data['group'] ?? null) {
            'requests' => $query->where('status', Booking::REQUESTED)->orderBy('start_date'),
            'upcoming' => $query->whereIn('status', [Booking::REQUESTED, Booking::ACCEPTED, Booking::CONFIRMED])->orderBy('start_date'),
            'active' => $query->whereIn('status', [Booking::ACTIVE, Booking::RETURNED])->orderBy('end_date'),
            'past' => $query->whereIn('status', [Booking::COMPLETED, Booking::CANCELLED, Booking::DECLINED, Booking::EXPIRED])->latest('updated_at'),
            default => $query->latest(),
        };

        $meta = [];
        if ($role === 'owner') {
            $payouts = Payout::where('owner_id', $user->id)->where('status', '!=', Payout::CANCELLED);
            $meta['earnings'] = [
                'total' => round((float) (clone $payouts)->sum('amount'), 2),
                'total_rentals' => (clone $payouts)->count(),
                'pending' => round((float) (clone $payouts)->whereIn('status', [Payout::PENDING, Payout::AVAILABLE])->sum('amount'), 2),
                'pending_rentals' => (clone $payouts)->whereIn('status', [Payout::PENDING, Payout::AVAILABLE])->count(),
                'paid' => round((float) (clone $payouts)->where('status', Payout::PAID)->sum('amount'), 2),
                'paid_rentals' => (clone $payouts)->where('status', Payout::PAID)->count(),
            ];
            $meta['items_count'] = $user->items()->count();
        }

        return BookingResource::collection($query->paginate($data['per_page'] ?? 20))->additional(['meta' => $meta]);
    }

    /** "Send request" from the Rental request screen. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'delivery' => ['nullable', 'boolean'],
            'delivery_address' => ['nullable', 'required_if:delivery,true,1', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = $this->bookings->request($request->user(), Item::findOrFail($data['item_id']), $data);

        return response()->json(['data' => new BookingResource($booking->load(self::RELATIONS))], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);

        return response()->json(['data' => new BookingResource($booking->load(self::RELATIONS))]);
    }

    public function accept(Request $request, Booking $booking): JsonResponse
    {
        return $this->respond($this->bookings->accept($booking, $request->user()));
    }

    public function decline(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->respond($this->bookings->decline($booking, $request->user(), $data['reason'] ?? null));
    }

    /** "Pay C$66" on Checkout. Returns 402 when the card is declined so the app shows "Payment not completed". */
    public function pay(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer'],
            'agree_terms' => ['accepted'],
            'simulate_failure' => ['nullable', 'boolean'],
        ]);

        $method = PaymentMethod::where('user_id', $request->user()->id)->findOrFail($data['payment_method_id']);
        $simulate = app()->environment('production') ? false : (bool) ($data['simulate_failure'] ?? false);

        $result = $this->bookings->pay($booking, $request->user(), $method, $simulate);

        return response()->json([
            'success' => $result['payment']->status === 'succeeded',
            'message' => $result['payment']->status === 'succeeded' ? 'Booking confirmed' : 'Payment not completed',
            'error' => $result['payment']->failure_reason,
            'payment' => new PaymentResource($result['payment']),
            'data' => new BookingResource($result['booking']->load(self::RELATIONS)),
            'refund_estimate' => $this->pricing->refundEstimate($result['booking']),
        ], $result['payment']->status === 'succeeded' ? 200 : 402);
    }

    public function refundEstimate(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);

        return response()->json(['data' => $this->pricing->refundEstimate($booking)]);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:1000']]);
        $reason = $data['reason'].(! empty($data['details']) ? ' – '.$data['details'] : '');

        return $this->respond($this->bookings->cancel($booking, $request->user(), $reason));
    }

    /** "Pickup condition check" → Confirm handoff. multipart: photos[], works, accessories_included, scratches_noted, notes */
    public function pickup(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);
        $data = $request->validate([
            'photos' => ['nullable', 'array', 'max:'.config('neighbours.max_check_photos')],
            'photos.*' => ['image', 'max:8192'],
            'works' => ['nullable', 'boolean'],
            'accessories_included' => ['nullable', 'boolean'],
            'scratches_noted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $paths = $this->storeUploads($request->file('photos', []), "bookings/{$booking->id}/pickup");

        return $this->respond($this->bookings->pickup($booking, $request->user(), $data, $paths));
    }

    /**
     * "Return condition check" → Confirm return (renter).
     * accessories_checklist: [{"name":"Exhaust hose","returned":true}, ...]
     */
    public function returnItem(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);
        $data = $request->validate([
            'photos' => ['nullable', 'array', 'max:'.config('neighbours.max_check_photos')],
            'photos.*' => ['image', 'max:8192'],
            'accessories_checklist' => ['nullable', 'array'],
            'accessories_checklist.*.name' => ['required', 'string', 'max:80'],
            'accessories_checklist.*.returned' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'returned_at' => ['nullable', 'date'],
        ]);

        $paths = $this->storeUploads($request->file('photos', []), "bookings/{$booking->id}/return");

        return $this->respond($this->bookings->markReturned($booking, $request->user(), $data, $paths));
    }

    /** Owner confirms the item came back OK → completes booking and releases payout. */
    public function confirmReturn(Request $request, Booking $booking): JsonResponse
    {
        return $this->respond($this->bookings->confirmReturn($booking, $request->user()));
    }

    public function review(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->bookings->review($booking, $request->user(), $data['rating'], $data['comment'] ?? null);

        return response()->json(['data' => new ReviewResource($review->load('reviewer'))], 201);
    }

    /** Preview cost before sending an extension request. */
    public function extensionQuote(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);
        $data = $request->validate(['new_end_date' => ['required', 'date', 'after:'.$booking->end_date->toDateString()]]);
        $newEnd = Carbon::parse($data['new_end_date']);

        return response()->json([
            'data' => $this->pricing->extensionQuote($booking, $newEnd),
            'available' => $booking->item->isAvailable($booking->end_date->copy()->addDay(), $newEnd, $booking->id),
        ]);
    }

    public function requestExtension(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'new_end_date' => ['required', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $ext = $this->bookings->requestExtension($booking, $request->user(), $data['new_end_date'], $data['message'] ?? null);

        return response()->json(['data' => new ExtensionResource($ext)], 201);
    }

    public function approveExtension(Request $request, BookingExtension $extension): JsonResponse
    {
        $ext = $this->bookings->respondToExtension($extension, $request->user(), true);

        return response()->json([
            'data' => new ExtensionResource($ext),
            'booking' => new BookingResource($ext->booking->fresh()->load(self::RELATIONS)),
        ], $ext->status === BookingExtension::APPROVED ? 200 : 402);
    }

    public function declineExtension(Request $request, BookingExtension $extension): JsonResponse
    {
        return response()->json(['data' => new ExtensionResource($this->bookings->respondToExtension($extension, $request->user(), false))]);
    }

    /** "Rental timeline": pickup, return and key dates. */
    public function timeline(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);
        $b = $booking;

        $steps = [
            ['key' => 'requested', 'label' => 'Request sent', 'at' => $b->created_at, 'done' => true],
            ['key' => 'accepted', 'label' => 'Owner accepted', 'at' => $b->accepted_at, 'done' => (bool) $b->accepted_at],
            ['key' => 'confirmed', 'label' => 'Payment confirmed', 'at' => $b->paid_at, 'done' => (bool) $b->paid_at],
            ['key' => 'pickup', 'label' => 'Pickup', 'at' => $b->picked_up_at ?? $b->startsAt(), 'done' => (bool) $b->picked_up_at, 'scheduled' => ! $b->picked_up_at],
            ['key' => 'return', 'label' => 'Return due', 'at' => $b->returned_at ?? $b->returnDueAt(), 'done' => (bool) $b->returned_at, 'scheduled' => ! $b->returned_at],
            ['key' => 'completed', 'label' => 'Completed', 'at' => $b->completed_at, 'done' => (bool) $b->completed_at],
        ];

        if (in_array($b->status, [Booking::CANCELLED, Booking::DECLINED, Booking::EXPIRED], true)) {
            $steps[] = ['key' => $b->status, 'label' => ucfirst($b->status), 'at' => $b->cancelled_at ?? $b->declined_at ?? $b->updated_at, 'done' => true];
        }

        return response()->json(['data' => array_map(fn ($s) => [...$s, 'at' => $s['at']?->toIso8601String()], $steps)]);
    }

    /** "View receipt" / "Download receipt" data. */
    public function receipt(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeParticipant($request, $booking);
        if (! $booking->paid_at) {
            throw ValidationException::withMessages(['booking' => 'No receipt yet – this booking has not been paid.']);
        }
        $booking->load(['item', 'renter', 'owner', 'payments.paymentMethod', 'extensions']);

        return response()->json(['data' => [
            'booking_code' => $booking->code,
            'status' => $booking->status,
            'item_title' => $booking->item->title,
            'owner_name' => $booking->owner->name,
            'renter_name' => $booking->renter->name,
            'start_date' => $booking->start_date->toDateString(),
            'end_date' => $booking->end_date->toDateString(),
            'days' => $booking->days,
            'currency' => config('neighbours.currency'),
            'lines' => array_values(array_filter([
                ['label' => "Rental ({$booking->days} days)", 'amount' => $booking->rental_subtotal],
                ['label' => 'Service fee', 'amount' => $booking->service_fee],
                $booking->delivery_fee > 0 ? ['label' => 'Delivery fee', 'amount' => $booking->delivery_fee] : null,
                $booking->taxes > 0 ? ['label' => 'Taxes', 'amount' => $booking->taxes] : null,
                $booking->security_deposit > 0 ? ['label' => 'Security deposit (refundable)', 'amount' => $booking->security_deposit] : null,
            ])),
            'total' => $booking->total,
            'refunded' => round((float) $booking->payments->where('type', 'refund')->where('status', 'succeeded')->sum('amount'), 2),
            'payments' => PaymentResource::collection($booking->payments),
            'paid_at' => $booking->paid_at->toIso8601String(),
        ]]);
    }

    private function respond(Booking $booking): JsonResponse
    {
        return response()->json(['data' => new BookingResource($booking->fresh()->load(self::RELATIONS))]);
    }

    private function authorizeParticipant(Request $request, Booking $booking): void
    {
        abort_unless($booking->isParticipant($request->user()), 403);
    }
}
