<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Payout;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data mirroring the Flutter prototype.
 * Configurable for Edmonton, AB or Indore, MP (or any coordinates via SEED_LAT/SEED_LNG/SEED_CITY/SEED_PROVINCE).
 * Every demo account uses the password: Password123
 */
class DatabaseSeeder extends Seeder
{
    private function config(): array
    {
        $city = env('SEED_CITY', 'Edmonton');
        $province = env('SEED_PROVINCE', 'AB');
        $isEdmonton = strcasecmp($city, 'Edmonton') === 0;

        if ($isEdmonton) {
            $areas = [
                'Mill Woods' => [53.4568, -113.4326],
                'Downtown' => [53.5444, -113.4909],
                'North Edmonton' => [53.6010, -113.4800],
                'West Edmonton' => [53.5225, -113.6240],
                'Oliver' => [53.5420, -113.5170],
                'Rutherford' => [53.4170, -113.5250],
            ];
            $postalAlex = 'T6K 2N5';
            $postalDryer = 'T6L 4X4';
            $addressDryer = '123 Mill Woods Road NW';
        } else {
            $baseLat = (float) env('SEED_LAT', 22.7179);
            $baseLng = (float) env('SEED_LNG', 75.8334);
            $areas = [
                'Vijay Nagar' => [$baseLat + 0.0354, $baseLng + 0.0603],
                'Palasia' => [$baseLat + 0.0065, $baseLng + 0.0505],
                'Rajwada' => [$baseLat + 0.0017, $baseLng + 0.0243],
                'Bhawarkua' => [$baseLat - 0.0253, $baseLng + 0.0342],
                'Annapurna' => [$baseLat - 0.0205, $baseLng - 0.0012],
                'Central' => [$baseLat, $baseLng],
            ];
            $postalAlex = '452010';
            $postalDryer = '452010';
            $addressDryer = 'Near Central Park';
        }

        return [$city, $province, $areas, $postalAlex, $postalDryer, $addressDryer];
    }

    public function run(): void
    {
        [$city, $province, $areas, $postalAlex, $postalDryer, $addressDryer] = $this->config();
        $areaKeys = array_keys($areas);

        $categories = collect([
            ['name' => 'Tools', 'slug' => 'tools', 'icon' => 'handyman'],
            ['name' => 'Appliances', 'slug' => 'appliances', 'icon' => 'local_laundry_service'],
            ['name' => 'Outdoors', 'slug' => 'outdoors', 'icon' => 'cabin'],
            ['name' => 'Events', 'slug' => 'events', 'icon' => 'celebration'],
        ])->map(fn ($c, $i) => Category::firstOrCreate(['slug' => $c['slug']], [...$c, 'sort_order' => $i]))->keyBy('name');

        $admin = $this->user('Admin', 'admin@neighboursrental.ca', $areaKeys[1] ?? $areaKeys[0], $areas, $city, $province);
        $admin->forceFill(['is_admin' => true])->save();

        $alex = $this->user('Alex Taylor', 'alex@example.com', $areaKeys[0], $areas, $city, $province, $postalAlex);
        $users = collect(['Maya', 'Taylor', 'Jamie', 'Sarah', 'Priya', 'Liam', 'Noor', 'Owen', 'Sam'])
            ->mapWithKeys(fn ($n) => [$n => $this->user($n, strtolower($n).'@example.com', $areaKeys[crc32($n) % count($areaKeys)], $areas, $city, $province)]);
        $users['Maya']->update(['bio' => "Hi! I love keeping great home and outdoor items in excellent condition and sharing them with neighbours. Feel free to reach out with any questions!"]);

        $dryer = $this->item($users['Maya'], $categories['Appliances'], $areaKeys[0], $areas, $city, $province, [
            'title' => 'Portable Dryer', 'brand' => 'Midea', 'model' => 'MD200D88W', 'condition' => 'like_new', 'daily_rate' => 20,
            'description' => 'Compact and powerful portable dryer, perfect for apartments, dorms, or small spaces. Easy to set up and use. Works great for everyday clothes, towels, and bedding.',
            'accessories' => ['Exhaust hose', 'Window vent adapter', 'User manual'], 'tags' => ['Portable', 'Compact', 'Easy setup'],
            'rental_rules' => 'Clean and return in same condition. Indoor use only.',
            'pickup_address' => $addressDryer, 'postal_code' => $postalDryer,
        ], 4.9, 18);

        $drill = $this->item($users['Taylor'], $categories['Tools'], $areaKeys[1] ?? $areaKeys[0], $areas, $city, $province, [
            'title' => 'Cordless Drill', 'brand' => 'Makita', 'model' => '18V LXT Drill Driver', 'condition' => 'good', 'daily_rate' => 15,
            'description' => 'Makita 18V LXT drill driver with two batteries and charger.', 'accessories' => ['2 batteries', 'Charger', 'Bit set'],
        ], 4.8, 31);

        $tent = $this->item($users['Jamie'], $categories['Outdoors'], $areaKeys[2] ?? $areaKeys[0], $areas, $city, $province, [
            'title' => 'Camping Tent', 'condition' => 'good', 'daily_rate' => 18, 'description' => '4-person dome tent, waterproof, quick setup.',
        ], 4.9, 18);

        $this->item($users['Sarah'], $categories['Events'], $areaKeys[3] ?? $areaKeys[0], $areas, $city, $province, [
            'title' => 'Event Canopy', 'condition' => 'like_new', 'daily_rate' => 50, 'description' => '10x20 ft pop-up canopy with side walls.',
            'offers_delivery' => true, 'delivery_radius_km' => 10, 'delivery_fee' => 15,
        ], 4.9, 12);

        foreach ([
            ['Priya', 'Stackable Dryer', $areaKeys[4] ?? $areaKeys[0], 25, ['Electric', 'Stackable', 'Large capacity'], 4.8, 12],
            ['Liam', 'Full Size Dryer', $areaKeys[0], 28, ['Full size', 'High capacity', 'Electric'], 4.9, 8],
            ['Noor', 'Compact Dryer', $areaKeys[1] ?? $areaKeys[0], 18, ['Compact', 'Portable', 'Energy efficient'], 4.7, 9],
            ['Owen', 'Premium Dryer', $areaKeys[2] ?? $areaKeys[0], 32, ['Large capacity', 'Multiple cycles', 'Modern'], 5.0, 6],
        ] as [$owner, $title, $area, $rate, $tags, $rating, $reviews]) {
            $this->item($users[$owner], $categories['Appliances'], $area, $areas, $city, $province, [
                'title' => $title, 'condition' => 'good', 'daily_rate' => $rate, 'tags' => $tags,
                'description' => "{$title} available for short-term rental.",
            ], $rating, $reviews);
        }

        // A pending listing so the admin queue isn't empty.
        $pending = $this->item($alex, $categories['Tools'], $areaKeys[0], $areas, $city, $province, [
            'title' => 'Hand Tool Set', 'condition' => 'good', 'daily_rate' => 10, 'description' => '120-piece hand tool set.',
        ], 0, 0);
        $pending->forceFill(['status' => Item::STATUS_PENDING, 'approved_at' => null])->save();

        // Alex's card + booking
        $card = PaymentMethod::firstOrCreate(
            ['user_id' => $alex->id, 'last4' => '4242'],
            ['brand' => 'VISA', 'exp_month' => 8, 'exp_year' => 2028, 'is_default' => true]
        );

        $start = now()->addDays(5)->startOfDay();
        $booking = Booking::firstOrCreate(
            ['code' => 'NR1042'],
            [
                'item_id' => $dryer->id, 'renter_id' => $alex->id, 'owner_id' => $users['Maya']->id,
                'start_date' => $start, 'end_date' => $start->copy()->addDays(2), 'pickup_time' => '10:00', 'return_time' => '10:00', 'days' => 3,
                'message' => "Hi Maya! I'd like to rent your dryer for a few days while mine is being repaired.",
                'daily_rate' => 20, 'rental_subtotal' => 60, 'service_fee' => 6, 'delivery_fee' => 0, 'taxes' => 0, 'security_deposit' => 0, 'total' => 66,
                'status' => Booking::CONFIRMED, 'cancellation_policy' => 'flexible',
                'accepted_at' => now()->subDay(), 'paid_at' => now()->subHours(20),
            ]
        );

        Payment::firstOrCreate(
            ['booking_id' => $booking->id, 'type' => 'charge'],
            ['user_id' => $alex->id, 'payment_method_id' => $card->id, 'status' => 'succeeded', 'amount' => 66, 'provider_reference' => 'ch_'.Str::random(20), 'description' => 'Booking NR1042']
        );
        Payout::firstOrCreate(
            ['booking_id' => $booking->id, 'owner_id' => $users['Maya']->id],
            ['amount' => 60, 'status' => Payout::PENDING]
        );

        // A pending request on Taylor's drill.
        Booking::firstOrCreate(
            ['code' => 'NR20315'],
            [
                'item_id' => $drill->id, 'renter_id' => $users['Sam']->id, 'owner_id' => $users['Taylor']->id,
                'start_date' => now()->addDays(10), 'end_date' => now()->addDays(10), 'days' => 1, 'message' => 'Hi! Is the drill still available this weekend?',
                'daily_rate' => 15, 'rental_subtotal' => 15, 'service_fee' => 1.5, 'total' => 16.5, 'status' => Booking::REQUESTED,
            ]
        );

        try {
            $messaging = app(MessagingService::class);
            $chat = $messaging->bookingConversation($booking);
            $messaging->post($chat, $users['Maya'], "Hi Alex! The portable dryer is ready for your booking. Would you like to pick it up at my place in {$city}?");
            $messaging->post($chat->load('participants'), $alex, "Hi Maya! Yes, I can pick it up around 10:00 AM. Is that time still good for you?");
            $messaging->post($chat->load('participants'), $users['Maya'], "Sounds good! I'll have the portable dryer ready for pickup.");
        } catch (\Throwable $e) {
            // Ignore conversation duplicate if already created
        }

        $alex->favoriteItems()->syncWithoutDetaching([$dryer->id, $tent->id, $drill->id]);

        AppNotification::firstOrCreate(
            ['user_id' => $alex->id, 'title' => 'Booking accepted'],
            ['type' => 'booking_accepted', 'category' => 'bookings', 'body' => 'Portable Dryer • NR1042 • Owned by Maya', 'data' => ['booking_id' => $booking->id]]
        );
    }

    private function user(string $name, string $email, string $area, array $areas, string $city, string $province, ?string $postal = null): User
    {
        [$lat, $lng] = $areas[$area];

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name, 'password' => 'Password123', 'email_verified_at' => now(),
                'city' => $city, 'province' => $province, 'postal_code' => $postal, 'location_label' => "{$city}, {$province}",
                'latitude' => $lat, 'longitude' => $lng,
            ]
        );
    }

    private function item(User $owner, Category $category, string $area, array $areas, string $city, string $province, array $attrs, float $rating, int $reviews): Item
    {
        [$lat, $lng] = $areas[$area];
        $item = Item::updateOrCreate(
            ['user_id' => $owner->id, 'title' => $attrs['title']],
            [
                'category_id' => $category->id, 'area' => $area, 'city' => $city, 'province' => $province,
                'latitude' => $lat + (mt_rand(-30, 30) / 10000), 'longitude' => $lng + (mt_rand(-30, 30) / 10000),
                'min_days' => 1, 'max_days' => 14, 'pickup_window_start' => '10:00', 'pickup_window_end' => '16:00',
                ...$attrs,
            ]
        );
        $item->forceFill([
            'status' => Item::STATUS_ACTIVE,
            'submitted_at' => now()->subWeek(),
            'approved_at' => now()->subWeek(),
            'rating_avg' => $rating,
            'reviews_count' => $reviews,
        ])->save();

        return $item;
    }
}
