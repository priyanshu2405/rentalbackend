<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $role = $viewer ? $this->roleOf($viewer) : null;
        $item = $this->item;
        $addressVisible = $role === 'owner' || ($role === 'renter' && in_array($this->status, Booking::PAID, true));

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'role' => $role,
            'item' => new ItemResource($this->whenLoaded('item')),
            'renter' => new UserSummaryResource($this->whenLoaded('renter')),
            'owner' => new UserSummaryResource($this->whenLoaded('owner')),

            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'pickup_time' => $this->pickup_time ? substr($this->pickup_time, 0, 5) : null,
            'return_time' => $this->return_time ? substr($this->return_time, 0, 5) : null,
            'return_due_at' => $this->returnDueAt()->toIso8601String(),
            'days' => $this->days,
            'message' => $this->message,

            'delivery' => $this->delivery,
            'delivery_address' => $this->when($role !== null, $this->delivery_address),
            'pickup_location' => [
                'label' => collect([$item?->area, $item?->city, $item?->province])->filter()->implode(', '),
                'address' => $addressVisible ? $item?->pickup_address : null,
                'postal_code' => $addressVisible ? $item?->postal_code : null,
                'latitude' => $addressVisible ? $item?->latitude : ($item?->latitude ? round($item->latitude, 2) : null),
                'longitude' => $addressVisible ? $item?->longitude : ($item?->longitude ? round($item->longitude, 2) : null),
                'note' => $addressVisible ? null : 'Exact address provided after payment.',
            ],

            'pricing' => [
                'currency' => config('neighbours.currency'),
                'daily_rate' => $this->daily_rate,
                'rental_subtotal' => $this->rental_subtotal,
                'service_fee' => $this->service_fee,
                'delivery_fee' => $this->delivery_fee,
                'taxes' => $this->taxes,
                'security_deposit' => $this->security_deposit,
                'total' => $this->total,
                'refund_amount' => $this->refund_amount,
                'owner_earnings' => $this->when($role === 'owner', fn () => $this->ownerEarnings()),
            ],
            'cancellation_policy' => $this->cancellation_policy,

            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,

            'extensions' => ExtensionResource::collection($this->whenLoaded('extensions')),
            'condition_checks' => ConditionCheckResource::collection($this->whenLoaded('conditionChecks')),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->id),
            'can_review' => $this->when($this->relationLoaded('reviews') && $viewer, fn () => $this->status === Booking::COMPLETED
                && ! $this->reviews->contains('reviewer_id', $viewer->id)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
