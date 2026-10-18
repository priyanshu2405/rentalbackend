<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $this->user_id;
        $attrs = $this->resource->getAttributes();

        // Non-owners only get an approximate position (~1 km) – exact address is shared after booking.
        $lat = $this->latitude;
        $lng = $this->longitude;
        if (! $isOwner && ! is_null($lat)) {
            $lat = round($lat, 2);
            $lng = round($lng, 2);
        }

        $photos = $this->relationLoaded('photos') ? $this->photos : collect();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'brand' => $this->brand,
            'model' => $this->model,
            'condition' => $this->condition,
            'description' => $this->description,
            'accessories' => $this->accessories ?? [],
            'tags' => $this->tags ?? [],
            'rental_rules' => $this->rental_rules,
            'category' => $this->whenLoaded('category', fn () => $this->category ? new CategoryResource($this->category) : null),

            'daily_rate' => $this->daily_rate,
            'currency' => config('neighbours.currency'),
            'min_days' => $this->min_days,
            'max_days' => $this->max_days,
            'security_deposit' => $this->security_deposit,
            'cancellation_policy' => $this->cancellation_policy,

            'pickup_window_start' => $this->pickup_window_start ? substr($this->pickup_window_start, 0, 5) : null,
            'pickup_window_end' => $this->pickup_window_end ? substr($this->pickup_window_end, 0, 5) : null,
            'advance_notice_hours' => $this->advance_notice_hours,

            'location' => [
                'area' => $this->area,
                'city' => $this->city,
                'province' => $this->province,
                'label' => collect([$this->area, $this->city, $this->province])->filter()->implode(', '),
                'latitude' => $lat,
                'longitude' => $lng,
                'pickup_address' => $this->when($isOwner, $this->pickup_address),
                'postal_code' => $this->when($isOwner, $this->postal_code),
            ],
            'distance_km' => array_key_exists('distance_km', $attrs) && ! is_null($attrs['distance_km']) ? round((float) $attrs['distance_km'], 1) : null,

            'offers_delivery' => $this->offers_delivery,
            'delivery_radius_km' => $this->delivery_radius_km,
            'delivery_fee' => $this->delivery_fee,

            'rating' => $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'cover_photo_url' => $photos->first()?->url,
            'photos' => $photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url])->values(),
            'is_favorite' => array_key_exists('is_favorite', $attrs) ? (bool) $attrs['is_favorite'] : false,
            'owner' => $this->whenLoaded('owner', fn () => new UserSummaryResource($this->owner)),

            'status' => $this->status,
            $this->mergeWhen($isOwner, fn () => [
                'rejection_reason' => $this->rejection_reason,
                'submitted_at' => $this->submitted_at?->toIso8601String(),
                'approved_at' => $this->approved_at?->toIso8601String(),
                'views_count' => $this->views_count,
                'upcoming_bookings_count' => $attrs['upcoming_bookings_count'] ?? null,
                'missing_fields' => $this->status === 'draft' ? $this->missingFieldsForSubmission() : [],
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
