<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingExtension;
use App\Models\ConditionCheck;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Payout;
use App\Models\Review;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All booking state transitions live here so controllers stay thin and the rules are in one place.
 *
 * requested → accepted → confirmed → active → returned → completed
 *     ↘ declined   ↘ expired / cancelled   ↘ cancelled
 */
class BookingService
{
    public function __construct(
        private PricingService $pricing,
        private PaymentGateway $gateway,
        private NotificationService $notifications,
        private MessagingService $messaging,
    ) {}

    // ------------------------------------------------------------------ request / respond

    public function request(User $renter, Item $item, array $data): Booking
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $delivery = (bool) ($data['delivery'] ?? false);

        if ($item->user_id === $renter->id) {
            $this->fail('item', 'You cannot rent your own item.');
        }
        if ($item->status !== Item::STATUS_ACTIVE) {
            $this->fail('item', 'This item is not available for rent right now.');
        }
        if ($delivery && ! $item->offers_delivery) {
            $this->fail('delivery', 'This item is pickup only.');
        }

        $days = $this->pricing->days($start, $end);
        if ($days < $item->min_days) {
            $this->fail('end_date', "The minimum rental period is {$item->min_days} day(s).");
        }
        if ($days > $item->max_days) {
            $this->fail('end_date', "The maximum rental period is {$item->max_days} day(s).");
        }

        $pickupAt = $start->copy()->setTimeFromTimeString($data['pickup_time'] ?? ($item->pickup_window_start ?? '00:00'));
        if ($item->advance_notice_hours > 0 && now()->diffInHours($pickupAt, false) < $item->advance_notice_hours) {
            $this->fail('start_date', "The owner needs at least {$item->advance_notice_hours} hours' notice.");
        }

        return DB::transaction(function () use ($renter, $item, $data, $start, $end, $delivery) {
            Item::whereKey($item->id)->lockForUpdate()->first();

            if (! $item->isAvailable($start, $end)) {
                $this->fail('start_date', 'Those dates are not available.');
            }

            $quote = $this->pricing->quote($item, $start, $end, $delivery);

            $booking = Booking::create([
                'code' => CodeGenerator::unique(Booking::class, 'NR', 5),
                'item_id' => $item->id,
                'renter_id' => $renter->id,
                'owner_id' => $item->user_id,
                'start_date' => $start,
                'end_date' => $end,
                'pickup_time' => $data['pickup_time'] ?? $item->pickup_window_start,
                'return_time' => $data['return_time'] ?? $item->pickup_window_start,
                'days' => $quote['days'],
                'delivery' => $delivery,
                'delivery_address' => $delivery ? ($data['delivery_address'] ?? null) : null,
                'message' => $data['message'] ?? null,
                'daily_rate' => $quote['daily_rate'],
                'rental_subtotal' => $quote['rental_subtotal'],
                'service_fee' => $quote['service_fee'],
                'delivery_fee' => $quote['delivery_fee'],
                'taxes' => $quote['taxes'],
                'security_deposit' => $quote['security_deposit'],
                'total' => $quote['total'],
                'status' => Booking::REQUESTED,
                'cancellation_policy' => $item->cancellation_policy,
            ]);

            $conversation = $this->messaging->bookingConversation($booking);
            if (! empty($data['message'])) {
                $this->messaging->post($conversation, $renter, $data['message']);
            }

            $this->notifications->send($booking->owner, 'booking_requested', 'bookings',
                "New rental request from {$renter->name}",
                "{$item->title} · {$start->format('M j')} – {$end->format('M j, Y')}",
                ['booking_id' => $booking->id, 'item_id' => $item->id]);

            return $booking;
        });
    }

    public function accept(Booking $booking, User $owner): Booking
    {
        $this->assertOwner($booking, $owner);
        $this->assertStatus($booking, [Booking::REQUESTED]);

        return DB::transaction(function () use ($booking) {
            Item::whereKey($booking->item_id)->lockForUpdate()->first();

            if (! $booking->item->isAvailable($booking->start_date, $booking->end_date, $booking->id)) {
                $this->fail('booking', 'These dates are already taken by another booking.');
            }

            $due = now()->addHours(config('neighbours.payment_window_hours'));
            $startsAt = $booking->startsAt();
            if ($due->gt($startsAt)) {
                $due = $startsAt->copy()->max(now()->addHour());
            }

            $booking->update(['status' => Booking::ACCEPTED, 'accepted_at' => now(), 'payment_due_at' => $due]);

            // Other pending requests for overlapping dates can no longer be honoured.
            Booking::where('item_id', $booking->item_id)
                ->where('id', '!=', $booking->id)
                ->where('status', Booking::REQUESTED)
                ->whereDate('start_date', '<=', $booking->end_date)
                ->whereDate('end_date', '>=', $booking->start_date)
                ->get()
                ->each(fn (Booking $other) => $this->decline($other, $booking->owner, 'Those dates were booked by someone else.'));

            $this->notifications->send($booking->renter, 'booking_accepted', 'bookings',
                "{$booking->owner->name} accepted your request!",
                "Complete your payment by {$due->format('M j, Y \a\t g:i A')} to secure {$booking->item->title}.",
                ['booking_id' => $booking->id]);

            return $booking->fresh();
        });
    }

    public function decline(Booking $booking, User $owner, ?string $reason = null): Booking
    {
        $this->assertOwner($booking, $owner);
        $this->assertStatus($booking, [Booking::REQUESTED]);

        $booking->update(['status' => Booking::DECLINED, 'declined_at' => now(), 'decline_reason' => $reason]);

        $this->notifications->send($booking->renter, 'booking_declined', 'bookings',
            'Rental request declined',
            "{$booking->owner->name} can't lend {$booking->item->title} for those dates.".($reason ? " Reason: {$reason}" : ''),
            ['booking_id' => $booking->id]);

        return $booking;
    }

    // ------------------------------------------------------------------ payment

    /** @return array{booking: Booking, payment: Payment} */
    public function pay(Booking $booking, User $renter, PaymentMethod $method, bool $simulateFailure = false): array
    {
        $this->assertRenter($booking, $renter);
        $this->assertStatus($booking, [Booking::ACCEPTED]);

        if ($method->user_id !== $renter->id) {
            $this->fail('payment_method_id', 'Invalid payment method.');
        }
        if ($booking->payment_due_at && $booking->payment_due_at->isPast()) {
            $this->expire($booking);
            $this->fail('booking', 'The payment window has passed and the dates were released.');
        }

        $result = $this->gateway->charge($method, $booking->total, config('neighbours.currency'),
            "Booking {$booking->code} – {$booking->item->title}", ['simulate_failure' => $simulateFailure]);

        return DB::transaction(function () use ($booking, $renter, $method, $result) {
            $payment = Payment::create([
                'user_id' => $renter->id,
                'booking_id' => $booking->id,
                'payment_method_id' => $method->id,
                'type' => Payment::CHARGE,
                'status' => $result['success'] ? Payment::SUCCEEDED : Payment::FAILED,
                'amount' => $booking->total,
                'currency' => config('neighbours.currency'),
                'provider_reference' => $result['reference'],
                'failure_reason' => $result['error'],
                'description' => "Booking {$booking->code}",
            ]);

            if (! $result['success']) {
                $this->notifications->send($renter, 'payment_failed', 'bookings', 'Payment not completed',
                    "Your booking {$booking->code} is unconfirmed. {$result['error']}", ['booking_id' => $booking->id]);

                return ['booking' => $booking, 'payment' => $payment];
            }

            $booking->update(['status' => Booking::CONFIRMED, 'paid_at' => now()]);

            Payout::updateOrCreate(['booking_id' => $booking->id], [
                'owner_id' => $booking->owner_id,
                'amount' => $booking->ownerEarnings(),
                'status' => Payout::PENDING,
            ]);

            $period = $booking->start_date->format('M j').' – '.$booking->end_date->format('M j, Y');
            $this->notifications->send($renter, 'booking_confirmed', 'bookings', 'Booking confirmed',
                "{$booking->item->title} · {$period}. Booking {$booking->code}.", ['booking_id' => $booking->id]);
            $this->notifications->send($booking->owner, 'booking_paid', 'bookings', "{$renter->name} paid for {$booking->item->title}",
                "Pickup on {$booking->start_date->format('D, M j')}. Your earnings: C\$".number_format($booking->ownerEarnings(), 2),
                ['booking_id' => $booking->id]);

            return ['booking' => $booking->fresh(), 'payment' => $payment];
        });
    }

    // ------------------------------------------------------------------ cancel / expire

    public function cancel(Booking $booking, User $user, string $reason): Booking
    {
        if (! $booking->isParticipant($user)) {
            abort(403);
        }
        $this->assertStatus($booking, [Booking::REQUESTED, Booking::ACCEPTED, Booking::CONFIRMED]);

        return DB::transaction(function () use ($booking, $user, $reason) {
            $refund = 0.0;

            if ($booking->status === Booking::CONFIRMED) {
                // Owner cancellations always refund the renter in full.
                $refund = $user->id === $booking->owner_id
                    ? $booking->total
                    : $this->pricing->refundEstimate($booking)['refund_amount'];

                if ($refund > 0) {
                    $this->refund($booking, $refund, "Cancellation refund {$booking->code}");
                }
                $booking->payout?->update(['status' => Payout::CANCELLED]);
            }

            $booking->update([
                'status' => Booking::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
                'refund_amount' => $refund,
            ]);

            $other = $user->id === $booking->renter_id ? $booking->owner : $booking->renter;
            $this->notifications->send($other, 'booking_cancelled', 'bookings', "Booking {$booking->code} was cancelled",
                "{$user->name} cancelled the rental of {$booking->item->title}. Reason: {$reason}", ['booking_id' => $booking->id]);

            if ($refund > 0) {
                $this->notifications->send($booking->renter, 'refund_issued', 'updates', 'Refund on its way',
                    'C$'.number_format($refund, 2)." will be returned to your card for {$booking->code}.", ['booking_id' => $booking->id]);
            }

            return $booking->fresh();
        });
    }

    public function expire(Booking $booking): void
    {
        if ($booking->status !== Booking::ACCEPTED) {
            return;
        }
        $booking->update(['status' => Booking::EXPIRED]);
        $this->notifications->send($booking->renter, 'booking_expired', 'bookings', 'Booking expired',
            "Payment for {$booking->code} wasn't received in time, so the dates were released.", ['booking_id' => $booking->id]);
        $this->notifications->send($booking->owner, 'booking_expired', 'bookings', 'Booking expired',
            "{$booking->renter->name} didn't pay for {$booking->code}. Your dates are open again.", ['booking_id' => $booking->id]);
    }

    // ------------------------------------------------------------------ pickup / return

    public function pickup(Booking $booking, User $user, array $data, array $photoPaths): Booking
    {
        if (! $booking->isParticipant($user)) {
            abort(403);
        }
        $this->assertStatus($booking, [Booking::CONFIRMED]);

        return DB::transaction(function () use ($booking, $user, $data, $photoPaths) {
            ConditionCheck::updateOrCreate(['booking_id' => $booking->id, 'type' => 'pickup'], [
                'user_id' => $user->id,
                'works' => $data['works'] ?? null,
                'accessories_included' => $data['accessories_included'] ?? null,
                'scratches_noted' => $data['scratches_noted'] ?? null,
                'notes' => $data['notes'] ?? null,
                'photos' => $photoPaths,
                'occurred_at' => now(),
            ]);

            $booking->update(['status' => Booking::ACTIVE, 'picked_up_at' => now()]);

            $other = $user->id === $booking->renter_id ? $booking->owner : $booking->renter;
            $this->notifications->send($other, 'pickup_confirmed', 'bookings', 'Pickup confirmed',
                "{$booking->item->title} was handed off. Return due {$booking->end_date->format('D, M j, Y')}.", ['booking_id' => $booking->id]);

            return $booking->fresh();
        });
    }

    public function markReturned(Booking $booking, User $renter, array $data, array $photoPaths): Booking
    {
        $this->assertRenter($booking, $renter);
        $this->assertStatus($booking, [Booking::ACTIVE]);

        return DB::transaction(function () use ($booking, $renter, $data, $photoPaths) {
            ConditionCheck::updateOrCreate(['booking_id' => $booking->id, 'type' => 'return'], [
                'user_id' => $renter->id,
                'accessories_checklist' => $data['accessories_checklist'] ?? null,
                'notes' => $data['notes'] ?? null,
                'photos' => $photoPaths,
                'occurred_at' => isset($data['returned_at']) ? Carbon::parse($data['returned_at']) : now(),
            ]);

            $booking->update(['status' => Booking::RETURNED, 'returned_at' => now()]);

            $this->notifications->send($booking->owner, 'item_returned', 'bookings', "{$renter->name} returned {$booking->item->title}",
                'Check the item and confirm the return to release your payout.', ['booking_id' => $booking->id]);

            return $booking->fresh();
        });
    }

    public function confirmReturn(Booking $booking, User $owner): Booking
    {
        $this->assertOwner($booking, $owner);
        $this->assertStatus($booking, [Booking::RETURNED, Booking::ACTIVE]);

        return DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => Booking::COMPLETED,
                'returned_at' => $booking->returned_at ?? now(),
                'completed_at' => now(),
            ]);

            // Security deposit goes back to the renter once the owner confirms condition.
            if ($booking->security_deposit > 0) {
                $this->refund($booking, $booking->security_deposit, "Deposit return {$booking->code}");
            }

            $availableAt = now()->addDays(config('neighbours.payout_delay_days'));
            $booking->payout?->update([
                'status' => Payout::AVAILABLE,
                'amount' => $booking->ownerEarnings(),
                'available_at' => $availableAt,
            ]);

            $this->notifications->send($booking->owner, 'payout_update', 'updates', 'Payout update',
                'Your payout of C$'.number_format($booking->ownerEarnings(), 2)." for {$booking->code} will be released {$availableAt->format('M j, Y')}.",
                ['booking_id' => $booking->id]);

            foreach ([[$booking->renter, $booking->owner], [$booking->owner, $booking->renter]] as [$to, $about]) {
                $this->notifications->send($to, 'review_reminder', 'reviews', 'Review reminder',
                    "How did your rental with {$about->name} go? Leave a review to help build trust in the community.",
                    ['booking_id' => $booking->id]);
            }

            return $booking->fresh();
        });
    }

    // ------------------------------------------------------------------ extensions

    public function requestExtension(Booking $booking, User $renter, string $newEndDate, ?string $message = null): BookingExtension
    {
        $this->assertRenter($booking, $renter);
        $this->assertStatus($booking, [Booking::CONFIRMED, Booking::ACTIVE]);

        $newEnd = Carbon::parse($newEndDate)->startOfDay();
        if ($newEnd->lte($booking->end_date)) {
            $this->fail('new_end_date', 'The new return date must be after the current one.');
        }
        if ($booking->extensions()->where('status', BookingExtension::PENDING)->exists()) {
            $this->fail('new_end_date', 'You already have a pending extension request.');
        }
        $item = $booking->item;
        $totalDays = $this->pricing->days($booking->start_date, $newEnd);
        if ($totalDays > $item->max_days) {
            $this->fail('new_end_date', "The maximum rental period is {$item->max_days} day(s).");
        }
        if (! $item->isAvailable($booking->end_date->copy()->addDay(), $newEnd, $booking->id)) {
            $this->fail('new_end_date', 'The item is not available for those extra days.');
        }

        $quote = $this->pricing->extensionQuote($booking, $newEnd);
        $extension = $booking->extensions()->create([
            'previous_end_date' => $booking->end_date,
            'new_end_date' => $newEnd,
            'extra_days' => $quote['extra_days'],
            'rental_amount' => $quote['rental_amount'],
            'service_fee' => $quote['service_fee'],
            'taxes' => $quote['taxes'],
            'total' => $quote['total'],
            'message' => $message,
            'status' => BookingExtension::PENDING,
        ]);

        $this->notifications->send($booking->owner, 'extension_requested', 'bookings', "{$renter->name} asked for more time",
            "Extend {$booking->item->title} to {$newEnd->format('M j, Y')} (+{$quote['extra_days']} day(s)).",
            ['booking_id' => $booking->id, 'extension_id' => $extension->id]);

        return $extension;
    }

    public function respondToExtension(BookingExtension $extension, User $owner, bool $approve): BookingExtension
    {
        $booking = $extension->booking;
        $this->assertOwner($booking, $owner);
        if ($extension->status !== BookingExtension::PENDING) {
            $this->fail('extension', 'This request has already been answered.');
        }

        if (! $approve) {
            $extension->update(['status' => BookingExtension::DECLINED, 'responded_at' => now()]);
            $this->notifications->send($booking->renter, 'extension_declined', 'bookings', 'Extension declined',
                "{$owner->name} couldn't extend {$booking->item->title}. Please return it by {$booking->end_date->format('M j, Y')}.",
                ['booking_id' => $booking->id]);

            return $extension;
        }

        $method = $booking->payments()->where('type', Payment::CHARGE)->where('status', Payment::SUCCEEDED)->latest()->first()?->paymentMethod
            ?? $booking->renter->defaultPaymentMethod();

        if (! $method) {
            $this->fail('extension', 'The renter has no payment method on file.');
        }

        $result = $this->gateway->charge($method, $extension->total, config('neighbours.currency'), "Extension for {$booking->code}");

        return DB::transaction(function () use ($extension, $booking, $method, $result) {
            Payment::create([
                'user_id' => $booking->renter_id,
                'booking_id' => $booking->id,
                'booking_extension_id' => $extension->id,
                'payment_method_id' => $method->id,
                'type' => Payment::CHARGE,
                'status' => $result['success'] ? Payment::SUCCEEDED : Payment::FAILED,
                'amount' => $extension->total,
                'currency' => config('neighbours.currency'),
                'provider_reference' => $result['reference'],
                'failure_reason' => $result['error'],
                'description' => "Extension {$booking->code} (+{$extension->extra_days} day)",
            ]);

            if (! $result['success']) {
                $extension->update(['status' => BookingExtension::PAYMENT_FAILED, 'responded_at' => now()]);
                $this->notifications->send($booking->renter, 'extension_payment_failed', 'bookings', 'Extension payment failed',
                    "We couldn't charge your card for the extension of {$booking->code}.", ['booking_id' => $booking->id]);

                return $extension;
            }

            $extension->update(['status' => BookingExtension::APPROVED, 'responded_at' => now()]);
            $booking->update([
                'end_date' => $extension->new_end_date,
                'days' => $booking->days + $extension->extra_days,
                'rental_subtotal' => $booking->rental_subtotal + $extension->rental_amount,
                'service_fee' => $booking->service_fee + $extension->service_fee,
                'taxes' => $booking->taxes + $extension->taxes,
                'total' => $booking->total + $extension->total,
            ]);
            $booking->payout?->update(['amount' => $booking->ownerEarnings()]);

            $this->notifications->send($booking->renter, 'extension_approved', 'bookings', 'Extension approved',
                "New return date: {$extension->new_end_date->format('D, M j, Y')}.", ['booking_id' => $booking->id]);

            return $extension;
        });
    }

    // ------------------------------------------------------------------ reviews

    public function review(Booking $booking, User $reviewer, int $rating, ?string $comment): Review
    {
        if (! $booking->isParticipant($reviewer)) {
            abort(403);
        }
        $this->assertStatus($booking, [Booking::COMPLETED]);
        if ($booking->reviews()->where('reviewer_id', $reviewer->id)->exists()) {
            $this->fail('rating', 'You already reviewed this rental.');
        }

        $isRenter = $reviewer->id === $booking->renter_id;

        $review = Review::create([
            'booking_id' => $booking->id,
            'item_id' => $booking->item_id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $isRenter ? $booking->owner_id : $booking->renter_id,
            'role' => $isRenter ? Review::ROLE_RENTER : Review::ROLE_OWNER,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $review->reviewee->recalculateRating();
        if ($isRenter) {
            $booking->item->recalculateRating();
        }

        $this->notifications->send($review->reviewee, 'new_review', 'reviews', "{$reviewer->name} left you a {$rating}★ review",
            $comment ? mb_strimwidth($comment, 0, 120, '…') : null, ['booking_id' => $booking->id, 'review_id' => $review->id]);

        return $review;
    }

    // ------------------------------------------------------------------ helpers

    public function refund(Booking $booking, float $amount, string $description): Payment
    {
        $charge = $booking->payments()->where('type', Payment::CHARGE)->where('status', Payment::SUCCEEDED)->oldest()->first();
        $result = $this->gateway->refund($charge?->provider_reference, $amount, config('neighbours.currency'));

        return Payment::create([
            'user_id' => $booking->renter_id,
            'booking_id' => $booking->id,
            'payment_method_id' => $charge?->payment_method_id,
            'type' => Payment::REFUND,
            'status' => $result['success'] ? Payment::SUCCEEDED : Payment::FAILED,
            'amount' => round($amount, 2),
            'currency' => config('neighbours.currency'),
            'provider_reference' => $result['reference'],
            'failure_reason' => $result['error'],
            'description' => $description,
        ]);
    }

    private function assertOwner(Booking $booking, User $user): void
    {
        if ($booking->owner_id !== $user->id) {
            abort(403, 'Only the item owner can do this.');
        }
    }

    private function assertRenter(Booking $booking, User $user): void
    {
        if ($booking->renter_id !== $user->id) {
            abort(403, 'Only the renter can do this.');
        }
    }

    private function assertStatus(Booking $booking, array $allowed): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            $this->fail('status', "This action isn't possible while the booking is {$booking->status}.");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
