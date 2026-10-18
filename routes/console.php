<?php

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Run the scheduler with a cron entry:  * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
*/

// Release dates when an accepted booking isn't paid in time.
Artisan::command('bookings:expire-unpaid', function (BookingService $service) {
    $expired = Booking::where('status', Booking::ACCEPTED)->where('payment_due_at', '<', now())->get();
    $expired->each(fn (Booking $b) => $service->expire($b));
    $this->info("Expired {$expired->count()} booking(s).");
})->purpose('Expire accepted bookings whose payment window has passed');

// "Pickup reminder" the day before + return-due reminders.
Artisan::command('bookings:send-reminders', function (NotificationService $notifications) {
    $tomorrow = now()->addDay()->toDateString();

    Booking::with(['item', 'renter', 'owner'])->where('status', Booking::CONFIRMED)
        ->whereDate('start_date', $tomorrow)->whereNull('reminder_sent_at')->get()
        ->each(function (Booking $b) use ($notifications) {
            $when = $b->start_date->format('M j, Y').($b->pickup_time ? ' at '.date('g:i A', strtotime($b->pickup_time)) : '');
            $notifications->send($b->renter, 'pickup_reminder', 'bookings', 'Pickup reminder',
                "Pick up {$b->item->title} tomorrow, {$when}.", ['booking_id' => $b->id]);
            $notifications->send($b->owner, 'pickup_reminder', 'bookings', 'Pickup reminder',
                "{$b->renter->name} will pick up the {$b->item->title} tomorrow, {$when}.", ['booking_id' => $b->id]);
            $b->update(['reminder_sent_at' => now()]);
        });

    Booking::with(['item', 'renter'])->where('status', Booking::ACTIVE)->whereDate('end_date', $tomorrow)->get()
        ->each(fn (Booking $b) => $notifications->send($b->renter, 'return_reminder', 'bookings', 'Return due tomorrow',
            "Please return {$b->item->title} by {$b->end_date->format('D, M j')}. Need more time? Request an extension.", ['booking_id' => $b->id]));

    $this->info('Reminders sent.');
})->purpose('Send pickup and return reminders');

Schedule::command('bookings:expire-unpaid')->everyFifteenMinutes();
Schedule::command('bookings:send-reminders')->hourly();
