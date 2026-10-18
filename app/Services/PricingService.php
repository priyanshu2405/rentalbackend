<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PricingService
{
    /** Rental days are counted inclusively: Oct 12 → Oct 14 = 3 days (matches the app). */
    public function days(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }

    public function quote(Item $item, CarbonInterface $start, CarbonInterface $end, bool $delivery = false): array
    {
        $days = $this->days($start, $end);
        $subtotal = round($item->daily_rate * $days, 2);
        $service = $this->serviceFee($subtotal);
        $deliveryFee = $delivery && $item->offers_delivery ? round($item->delivery_fee, 2) : 0.0;
        $taxes = $this->taxes($subtotal + $service + $deliveryFee);
        $deposit = round($item->security_deposit, 2);

        return [
            'currency' => config('neighbours.currency'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'daily_rate' => round($item->daily_rate, 2),
            'rental_subtotal' => $subtotal,
            'service_fee' => $service,
            'service_fee_percent' => (float) config('neighbours.service_fee_percent'),
            'delivery_fee' => $deliveryFee,
            'taxes' => $taxes,
            'security_deposit' => $deposit,
            'total' => round($subtotal + $service + $deliveryFee + $taxes + $deposit, 2),
            'owner_earnings' => round($subtotal + $deliveryFee, 2),
        ];
    }

    public function extensionQuote(Booking $booking, CarbonInterface $newEnd): array
    {
        $extraDays = (int) $booking->end_date->copy()->startOfDay()->diffInDays($newEnd->copy()->startOfDay());
        $rental = round($booking->daily_rate * $extraDays, 2);
        $service = $this->serviceFee($rental);
        $taxes = $this->taxes($rental + $service);

        return [
            'currency' => config('neighbours.currency'),
            'previous_end_date' => $booking->end_date->toDateString(),
            'new_end_date' => $newEnd->toDateString(),
            'extra_days' => $extraDays,
            'rental_amount' => $rental,
            'service_fee' => $service,
            'taxes' => $taxes,
            'total' => round($rental + $service + $taxes, 2),
        ];
    }

    /**
     * Refund the renter would get if they cancelled now.
     * Returns amount + a human-readable explanation + the full policy schedule (for the "View policy" sheet).
     */
    public function refundEstimate(Booking $booking, ?CarbonInterface $at = null): array
    {
        $at = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        $start = CarbonImmutable::instance($booking->startsAt());

        // Nothing has been charged yet → cancelling is free.
        if (in_array($booking->status, [Booking::REQUESTED, Booking::ACCEPTED], true)) {
            return [
                'refund_amount' => 0.0,
                'total_paid' => 0.0,
                'explanation' => 'You have not been charged yet, so cancelling is free.',
                'policy' => $booking->cancellation_policy,
                'schedule' => $this->schedule($booking),
            ];
        }

        // Only paid bookings that have not been picked up can be cancelled for a refund.
        if ($booking->status !== Booking::CONFIRMED) {
            return [
                'refund_amount' => 0.0,
                'total_paid' => in_array($booking->status, Booking::PAID, true) ? $booking->total : 0.0,
                'explanation' => 'This booking can no longer be cancelled. Report an issue if something went wrong.',
                'policy' => $booking->cancellation_policy,
                'schedule' => $this->schedule($booking),
            ];
        }

        $rental = $booking->rental_subtotal + $booking->delivery_fee;
        $deposit = $booking->security_deposit;
        $service = $booking->service_fee;
        $taxes = $booking->taxes;
        $hoursBefore = $at->diffInHours($start, false);

        if ($booking->cancellation_policy === 'moderate') {
            if ($hoursBefore >= 24 * 7) {
                $amount = $rental + $taxes + $deposit;
                $why = 'Full refund of the rental (service fee is non-refundable).';
            } elseif ($hoursBefore >= 24) {
                $amount = round($rental * 0.5, 2) + $deposit;
                $why = '50% refund of the rental. Deposit returned in full.';
            } else {
                $amount = $deposit;
                $why = 'Less than 24 hours before pickup: rental is non-refundable. Deposit returned.';
            }
        } else { // flexible
            if ($hoursBefore > 0) {
                $amount = $booking->total;
                $why = 'Flexible policy: full refund of the rental price and service fee before the rental starts.';
            } else {
                $amount = $deposit;
                $why = 'The rental has started; only the security deposit is refundable.';
            }
        }

        return [
            'refund_amount' => round(min($amount, $booking->total), 2),
            'total_paid' => $booking->total,
            'service_fee' => $service,
            'explanation' => $why,
            'policy' => $booking->cancellation_policy,
            'schedule' => $this->schedule($booking),
        ];
    }

    /** Date bands for the "Estimated refund (if cancelled)" table. */
    public function schedule(Booking $booking): array
    {
        $start = CarbonImmutable::instance($booking->startsAt());
        $rental = $booking->rental_subtotal + $booking->delivery_fee;

        if ($booking->cancellation_policy === 'moderate') {
            return [
                ['until' => $start->subDays(7)->toIso8601String(), 'label' => 'Full refund (minus service fee)', 'amount' => round($rental + $booking->taxes + $booking->security_deposit, 2)],
                ['until' => $start->subDay()->toIso8601String(), 'label' => '50% refund', 'amount' => round($rental * 0.5 + $booking->security_deposit, 2)],
                ['until' => $start->toIso8601String(), 'label' => 'No refund', 'amount' => round($booking->security_deposit, 2)],
            ];
        }

        return [
            ['until' => $start->toIso8601String(), 'label' => 'Full refund', 'amount' => round($booking->total, 2)],
            ['until' => null, 'label' => 'After rental start: no refund', 'amount' => round($booking->security_deposit, 2)],
        ];
    }

    private function serviceFee(float $amount): float
    {
        return round($amount * config('neighbours.service_fee_percent') / 100, 2);
    }

    private function taxes(float $amount): float
    {
        return round($amount * config('neighbours.tax_percent') / 100, 2);
    }
}
