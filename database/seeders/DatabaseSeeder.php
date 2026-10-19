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
 * Rich seed data with unique, realistic items tailored for EVERY neighbourhood in Edmonton, AB and Indore, MP.
 * Every demo account uses the password: Password123
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categories = collect([
            ['name' => 'Tools', 'slug' => 'tools', 'icon' => 'handyman'],
            ['name' => 'Appliances', 'slug' => 'appliances', 'icon' => 'local_laundry_service'],
            ['name' => 'Outdoors', 'slug' => 'outdoors', 'icon' => 'cabin'],
            ['name' => 'Events', 'slug' => 'events', 'icon' => 'celebration'],
        ])->map(fn ($c, $i) => Category::firstOrCreate(['slug' => $c['slug']], [...$c, 'sort_order' => $i]))->keyBy('name');

        // 2. Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@neighboursrental.ca'],
            [
                'name' => 'Admin', 'password' => 'Password123', 'email_verified_at' => now(),
                'city' => 'Edmonton', 'province' => 'AB', 'postal_code' => 'T5J 0K1',
                'location_label' => 'Downtown, Edmonton, AB', 'latitude' => 53.5444, 'longitude' => -113.4909,
            ]
        );
        $admin->forceFill(['is_admin' => true])->save();

        // 3. Neighbours / Owners across Edmonton & Indore
        $usersData = [
            'Alex' => ['name' => 'Alex Taylor', 'email' => 'alex@example.com', 'area' => 'Mill Woods', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.4568, 'lng' => -113.4326, 'postal' => 'T6K 2N5'],
            'Maya' => ['name' => 'Maya Lin', 'email' => 'maya@example.com', 'area' => 'Mill Woods', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.4572, 'lng' => -113.4318, 'postal' => 'T6L 4X4', 'bio' => 'Hi! I love keeping home and outdoor items in top condition and sharing them with neighbours.'],
            'Taylor' => ['name' => 'Taylor Reed', 'email' => 'taylor@example.com', 'area' => 'Oliver', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.5420, 'lng' => -113.5170, 'postal' => 'T5K 1X4'],
            'Jamie' => ['name' => 'Jamie Scott', 'email' => 'jamie@example.com', 'area' => 'North Edmonton', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.6010, 'lng' => -113.4800, 'postal' => 'T5E 5R8'],
            'Sarah' => ['name' => 'Sarah Connor', 'email' => 'sarah@example.com', 'area' => 'Rutherford', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.4170, 'lng' => -113.5250, 'postal' => 'T6W 1A1'],
            'Priya' => ['name' => 'Priya Patel', 'email' => 'priya@example.com', 'area' => 'Downtown', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.5444, 'lng' => -113.4909, 'postal' => 'T5J 2R7'],
            'Liam' => ['name' => 'Liam Wilson', 'email' => 'liam@example.com', 'area' => 'West Edmonton', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.5225, 'lng' => -113.6240, 'postal' => 'T5T 4M2'],
            'Noor' => ['name' => 'Noor Al-Mansoor', 'email' => 'noor@example.com', 'area' => 'Old Strathcona', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.5180, 'lng' => -113.4970, 'postal' => 'T6E 2A8'],
            'Owen' => ['name' => 'Owen Davies', 'email' => 'owen@example.com', 'area' => 'Windermere', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.4300, 'lng' => -113.6100, 'postal' => 'T6W 2S7'],
            'Chloe' => ['name' => 'Chloe Bennett', 'email' => 'chloe@example.com', 'area' => 'Clareview', 'city' => 'Edmonton', 'prov' => 'AB', 'lat' => 53.6050, 'lng' => -113.4100, 'postal' => 'T5Y 2L4'],
            'Sam' => ['name' => 'Sam Green', 'email' => 'sam@example.com', 'area' => 'Sherwood Park', 'city' => 'Strathcona County', 'prov' => 'AB', 'lat' => 53.5413, 'lng' => -113.2958, 'postal' => 'T8A 4Y2'],
            'Lucas' => ['name' => 'Lucas Roy', 'email' => 'lucas@example.com', 'area' => 'St. Albert', 'city' => 'St. Albert', 'prov' => 'AB', 'lat' => 53.6305, 'lng' => -113.6256, 'postal' => 'T8N 1N2'],
            // Indore owners
            'Rahul' => ['name' => 'Rahul Sharma', 'email' => 'rahul@example.com', 'area' => 'Vijay Nagar', 'city' => 'Indore', 'prov' => 'MP', 'lat' => 22.7533, 'lng' => 75.8937, 'postal' => '452010'],
            'Ananya' => ['name' => 'Ananya Joshi', 'email' => 'ananya@example.com', 'area' => 'Palasia', 'city' => 'Indore', 'prov' => 'MP', 'lat' => 22.7244, 'lng' => 75.8839, 'postal' => '452001'],
            'Amit' => ['name' => 'Amit Verma', 'email' => 'amit@example.com', 'area' => 'Rajwada', 'city' => 'Indore', 'prov' => 'MP', 'lat' => 22.7196, 'lng' => 75.8577, 'postal' => '452002'],
            'Pooja' => ['name' => 'Pooja Jain', 'email' => 'pooja@example.com', 'area' => 'Bhawarkua', 'city' => 'Indore', 'prov' => 'MP', 'lat' => 22.6926, 'lng' => 75.8676, 'postal' => '452014'],
            'Rohan' => ['name' => 'Rohan Gupta', 'email' => 'rohan@example.com', 'area' => 'Annapurna', 'city' => 'Indore', 'prov' => 'MP', 'lat' => 22.6974, 'lng' => 75.8322, 'postal' => '452009'],
        ];

        $users = collect($usersData)->mapWithKeys(function ($u, $key) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'], 'password' => 'Password123', 'email_verified_at' => now(),
                    'city' => $u['city'], 'province' => $u['prov'], 'postal_code' => $u['postal'],
                    'location_label' => "{$u['area']}, {$u['city']}, {$u['prov']}",
                    'latitude' => $u['lat'], 'longitude' => $u['lng'],
                    'bio' => $u['bio'] ?? null,
                ]
            );
            return [$key => $user];
        });

        // 4. Unique items for EACH neighbourhood
        $itemsCatalog = [
            // --- Mill Woods, Edmonton ---
            [
                'owner' => 'Maya', 'category' => 'Appliances', 'area' => 'Mill Woods', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4568, 'lng' => -113.4326, 'address' => '123 Mill Woods Road NW', 'postal' => 'T6L 4X4',
                'title' => 'Portable Dryer', 'brand' => 'Midea', 'model' => 'MD200D88W', 'condition' => 'like_new', 'daily_rate' => 20,
                'description' => 'Compact and powerful portable dryer, perfect for apartments, dorms, or small spaces. Easy to set up and use. Works great for everyday clothes, towels, and bedding.',
                'accessories' => ['Exhaust hose', 'Window vent adapter', 'User manual'], 'tags' => ['Portable', 'Compact', 'Easy setup'],
                'rental_rules' => 'Clean lint filter and return in same condition. Indoor use only.', 'rating' => 4.9, 'reviews' => 18,
            ],
            [
                'owner' => 'Maya', 'category' => 'Tools', 'area' => 'Mill Woods', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4580, 'lng' => -113.4350, 'address' => '45 Mill Woods Town Centre', 'postal' => 'T6K 3S9',
                'title' => 'Little Giant 22ft Multi-Position Ladder', 'brand' => 'Little Giant', 'model' => 'Leveler M22', 'condition' => 'good', 'daily_rate' => 18,
                'description' => 'Versatile ladder converts to stepladder, extension ladder, 90-degree, and scaffolding. Built-in ratchet levelers for uneven ground.',
                'accessories' => ['Work platform accessory', 'Instruction manual'], 'tags' => ['Ladder', 'Heavy Duty', 'Painting', 'Roofing'],
                'rental_rules' => 'Inspect locking pins before each use. Max weight capacity 300 lbs.', 'rating' => 4.8, 'reviews' => 14,
            ],

            // --- Oliver, Edmonton ---
            [
                'owner' => 'Taylor', 'category' => 'Tools', 'area' => 'Oliver', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5420, 'lng' => -113.5170, 'address' => '10230 114 St NW', 'postal' => 'T5K 1X4',
                'title' => 'Cordless Drill Driver Kit', 'brand' => 'Makita', 'model' => '18V LXT Brushless', 'condition' => 'good', 'daily_rate' => 15,
                'description' => 'High performance Makita 18V LXT drill driver with variable speed and two lithium-ion batteries. Perfect for home renovations and carpentry.',
                'accessories' => ['2x 4.0Ah batteries', 'Rapid charger', '30-piece bit set', 'Carrying case'], 'tags' => ['Drill', 'Cordless', 'Woodworking'],
                'rental_rules' => 'Return with fully charged batteries and clean bits in case.', 'rating' => 4.8, 'reviews' => 31,
            ],
            [
                'owner' => 'Taylor', 'category' => 'Appliances', 'area' => 'Oliver', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5435, 'lng' => -113.5190, 'address' => '10160 118 St NW', 'postal' => 'T5K 2L3',
                'title' => 'Singer Heavy Duty Sewing Machine', 'brand' => 'Singer', 'model' => '4452', 'condition' => 'like_new', 'daily_rate' => 16,
                'description' => 'Extra-high sewing speed with stainless steel bed plate and heavy-duty metal frame. Handles denim, canvas, and leather with ease.',
                'accessories' => ['Foot pedal', 'Assorted needles', 'Walking foot', 'Bobbins'], 'tags' => ['Sewing', 'Crafts', 'Tailoring', 'Fashion'],
                'rental_rules' => 'Thread correctly according to diagram. Clean lint tray after use.', 'rating' => 5.0, 'reviews' => 9,
            ],

            // --- Downtown, Edmonton ---
            [
                'owner' => 'Priya', 'category' => 'Events', 'area' => 'Downtown', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5444, 'lng' => -113.4909, 'address' => '10405 Jasper Ave NW', 'postal' => 'T5J 2R7',
                'title' => 'Sony 4K Home Cinema Projector & 100" Screen', 'brand' => 'Sony', 'model' => 'VPL-X3000', 'condition' => 'like_new', 'daily_rate' => 45,
                'description' => 'Ultra-bright 4K HDR home theatre projector with tripod fold-out 100-inch screen. Ideal for backyard movie nights, sports screenings, and presentations.',
                'accessories' => ['100" portable stand screen', 'HDMI cable (15m)', 'Remote control', 'Padded travel case'], 'tags' => ['Projector', 'Movie Night', '4K', 'Home Theater'],
                'rental_rules' => 'Handle lens carefully with lens cap on when transporting. Avoid rain/moisture.', 'rating' => 4.9, 'reviews' => 22,
            ],
            [
                'owner' => 'Priya', 'category' => 'Events', 'area' => 'Downtown', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5460, 'lng' => -113.4880, 'address' => '10025 102A Ave NW', 'postal' => 'T5J 0G7',
                'title' => 'JBL PartyBox 310 Bluetooth Sound System', 'brand' => 'JBL', 'model' => 'PartyBox 310', 'condition' => 'good', 'daily_rate' => 35,
                'description' => '240W booming JBL pro sound with dazzling light sync, 18-hour battery, and built-in wheels with telescopic handle. Includes wireless microphone.',
                'accessories' => ['Wireless microphone', 'Charging cable', 'Aux cord'], 'tags' => ['Speaker', 'DJ', 'Party', 'Music', 'Wedding'],
                'rental_rules' => 'Indoor/covered outdoor use only. Do not expose to heavy rain.', 'rating' => 5.0, 'reviews' => 17,
            ],

            // --- North Edmonton ---
            [
                'owner' => 'Jamie', 'category' => 'Outdoors', 'area' => 'North Edmonton', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.6010, 'lng' => -113.4800, 'address' => '13704 97 St NW', 'postal' => 'T5E 5R8',
                'title' => 'Coleman 4-Person Dome Camping Tent', 'brand' => 'Coleman', 'model' => 'Sundome 4P', 'condition' => 'good', 'daily_rate' => 18,
                'description' => 'Waterproof WeatherTec camping tent with rainfly and ground stakes. Quick 10-minute setup with snag-free continuous pole sleeves.',
                'accessories' => ['Rainfly', 'Ground stakes (12)', 'Guylines', 'Carrying duffel'], 'tags' => ['Camping', 'Tent', 'Outdoor', 'Jasper', 'Banff'],
                'rental_rules' => 'Dry completely before packing into bag to prevent mildew.', 'rating' => 4.9, 'reviews' => 18,
            ],
            [
                'owner' => 'Jamie', 'category' => 'Tools', 'area' => 'North Edmonton', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.6025, 'lng' => -113.4820, 'address' => '14220 93 St NW', 'postal' => 'T5E 3S1',
                'title' => 'Sun Joe 2030 PSI High-Pressure Washer', 'brand' => 'Sun Joe', 'model' => 'SPX3000', 'condition' => 'good', 'daily_rate' => 22,
                'description' => 'Powerful 14.5-amp motor generates up to 2030 PSI. Features dual detergent tanks and 5 Quick-Connect spray tips for decks, driveways, and cars.',
                'accessories' => ['5 spray nozzles', '20ft high-pressure hose', 'Garden hose adapter'], 'tags' => ['Pressure Washer', 'Driveway Cleaning', 'Car Wash'],
                'rental_rules' => 'Ensure water is flowing before turning motor on to prevent pump burn.', 'rating' => 4.7, 'reviews' => 13,
            ],

            // --- Rutherford, Edmonton ---
            [
                'owner' => 'Sarah', 'category' => 'Events', 'area' => 'Rutherford', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4170, 'lng' => -113.5250, 'address' => '1150 Rutherford Rd SW', 'postal' => 'T6W 1A1',
                'title' => '10x20 ft Commercial Pop-Up Event Canopy', 'brand' => 'Eurmax', 'model' => 'Standard 10x20', 'condition' => 'like_new', 'daily_rate' => 50,
                'description' => 'Heavy-duty commercial popup canopy with waterproof top and 4 removable sidewalls with church windows and zippered doors.',
                'accessories' => ['4 sidewalls', '4 weight sandbags', 'Ground anchor stakes', 'Roller bag'], 'tags' => ['Canopy', 'Gazebo', 'Events', 'Markets', 'BBQ'],
                'rental_rules' => 'Must use weight sandbags or stakes. Do not leave unattended in severe wind.', 'rating' => 4.9, 'reviews' => 12,
            ],
            [
                'owner' => 'Sarah', 'category' => 'Appliances', 'area' => 'Rutherford', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4185, 'lng' => -113.5230, 'address' => '2010 Blackmud Creek Dr SW', 'postal' => 'T6W 1C5',
                'title' => 'Rug Doctor Pro Deep Carpet Cleaner', 'brand' => 'Rug Doctor', 'model' => 'Pro Deep', 'condition' => 'like_new', 'daily_rate' => 24,
                'description' => 'Commercial grade vibrating brush motor pulls deep dirt and pet stains from carpets, rugs, and upholstery. Includes motorized hand tool.',
                'accessories' => ['Upholstery hand tool hose', 'Measuring cup'], 'tags' => ['Carpet Cleaner', 'Deep Clean', 'Pet Stains'],
                'rental_rules' => 'Empty dirty water tank and rinse cleanly before return.', 'rating' => 4.8, 'reviews' => 16,
            ],

            // --- Old Strathcona, Edmonton ---
            [
                'owner' => 'Noor', 'category' => 'Outdoors', 'area' => 'Old Strathcona', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5180, 'lng' => -113.4970, 'address' => '10310 82 Ave NW', 'postal' => 'T6E 2A8',
                'title' => 'Thule Chariot 2-Child Bike Trailer & Stroller', 'brand' => 'Thule', 'model' => 'Chariot Cross 2', 'condition' => 'like_new', 'daily_rate' => 22,
                'description' => 'All-in-one bike trailer, jogger, and stroller. Comfortable reclining seats, suspension, and weather cover for kids.',
                'accessories' => ['Bike hitch arm with axle mount', 'Stroller wheels', 'Safety flag', 'Sunshade'], 'tags' => ['Bike Trailer', 'Kids', 'Cycling', 'Stroller'],
                'rental_rules' => 'Children must wear helmets while riding. Wipe down seat cushions.', 'rating' => 5.0, 'reviews' => 11,
            ],
            [
                'owner' => 'Noor', 'category' => 'Appliances', 'area' => 'Old Strathcona', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5195, 'lng' => -113.4950, 'address' => '8305 105 St NW', 'postal' => 'T6E 4H4',
                'title' => 'Bissell Little Green Pro Portable Spot Cleaner', 'brand' => 'Bissell', 'model' => 'SpotClean Pro', 'condition' => 'good', 'daily_rate' => 14,
                'description' => 'Powerful portable carpet, rug, and car interior cleaner. Strong suction removes tough stains from couches, stairs, and vehicle upholstery.',
                'accessories' => ['3" tough stain tool', 'HydroRinse self-cleaning tool'], 'tags' => ['Auto Detailing', 'Spot Cleaner', 'Upholstery'],
                'rental_rules' => 'Rinse recovery tank and suction nozzle after use.', 'rating' => 4.8, 'reviews' => 20,
            ],

            // --- West Edmonton ---
            [
                'owner' => 'Liam', 'category' => 'Tools', 'area' => 'West Edmonton', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5225, 'lng' => -113.6240, 'address' => '8882 170 St NW', 'postal' => 'T5T 4M2',
                'title' => 'Milwaukee SDS-Plus Rotary Hammer Drill', 'brand' => 'Milwaukee', 'model' => 'M18 FUEL 1"', 'condition' => 'good', 'daily_rate' => 28,
                'description' => 'Heavy duty concrete and masonry hammer drill with chipping mode. Easily drills into foundation walls and anchors.',
                'accessories' => ['Assorted SDS drill bits (3/16 to 1/2")', 'Chisel bit', 'Side handle', 'Hard case'], 'tags' => ['Concrete', 'Hammer Drill', 'Demolition'],
                'rental_rules' => 'Wear ear and eye protection during operation. Clean dust off tool body.', 'rating' => 4.9, 'reviews' => 15,
            ],
            [
                'owner' => 'Liam', 'category' => 'Outdoors', 'area' => 'West Edmonton', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.5240, 'lng' => -113.6210, 'address' => '17804 90 Ave NW', 'postal' => 'T5T 1L6',
                'title' => 'Intex Explorer K2 2-Person Inflatable Kayak', 'brand' => 'Intex', 'model' => 'Explorer K2', 'condition' => 'good', 'daily_rate' => 22,
                'description' => 'Sporty 2-person inflatable kayak with adjustable inflatable seats, removable skeg for directional stability, and quick inflation.',
                'accessories' => ['2x aluminium 86" oars', 'High-output hand pump', '2x life jackets (Adult M & L)', 'Carry bag'], 'tags' => ['Kayak', 'Paddling', 'North Saskatchewan River', 'Lake'],
                'rental_rules' => 'Dry fully before storing in carry bag. Life jackets must be worn.', 'rating' => 4.8, 'reviews' => 19,
            ],

            // --- Windermere, Edmonton ---
            [
                'owner' => 'Owen', 'category' => 'Outdoors', 'area' => 'Windermere', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4300, 'lng' => -113.6100, 'address' => '6104 Currents Dr NW', 'postal' => 'T6W 2S7',
                'title' => 'iROCKER 11ft All-Around Inflatable Paddle Board', 'brand' => 'iROCKER', 'model' => 'Cruiser Ultra 11\'', 'condition' => 'like_new', 'daily_rate' => 28,
                'description' => 'Premium military-grade drop-stitch SUP board with triple fins, front & back cargo bungee, and comfortable non-slip deck pad.',
                'accessories' => ['Carbon fiber adjustable paddle', 'Dual-chamber triple-action pump', 'Safety ankle leash', 'Backpack bag'], 'tags' => ['SUP', 'Paddleboard', 'Lake Day', 'Summer'],
                'rental_rules' => 'Inflate up to 15 PSI. Rinse off lake sand with fresh water before packing.', 'rating' => 5.0, 'reviews' => 14,
            ],
            [
                'owner' => 'Owen', 'category' => 'Appliances', 'area' => 'Windermere', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.4320, 'lng' => -113.6080, 'address' => '324 Windermere Rd NW', 'postal' => 'T6W 0V4',
                'title' => 'Breville Barista Touch Espresso Machine', 'brand' => 'Breville', 'model' => 'BES880BSS', 'condition' => 'like_new', 'daily_rate' => 30,
                'description' => 'Automated touch screen espresso maker with integrated conical burr grinder and automatic microfoam milk texturing.',
                'accessories' => ['54mm stainless steel portafilter', 'Milk frothing jug', 'Single & double cup filters', 'Tamper'], 'tags' => ['Espresso', 'Coffee Bar', 'Brunch', 'Special Occasion'],
                'rental_rules' => 'Use filtered water only. Run rinse cycle after frothing milk.', 'rating' => 4.9, 'reviews' => 10,
            ],

            // --- Sherwood Park ---
            [
                'owner' => 'Sam', 'category' => 'Tools', 'area' => 'Sherwood Park', 'city' => 'Strathcona County', 'prov' => 'AB',
                'lat' => 53.5413, 'lng' => -113.2958, 'address' => '2016 Sherwood Dr', 'postal' => 'T8A 4Y2',
                'title' => 'Honda EU2200i Super Quiet Inverter Generator', 'brand' => 'Honda', 'model' => 'EU2200i', 'condition' => 'like_new', 'daily_rate' => 40,
                'description' => 'Ultra-quiet 2200W clean inverter power for sensitive electronics, power tools, camping, and outdoor events. Runs up to 8 hours on a single tank.',
                'accessories' => ['Heavy duty extension cord', 'Operating manual'], 'tags' => ['Generator', 'Power', 'Outdoor Event', 'Camping', 'Backup'],
                'rental_rules' => 'Use regular unleaded gasoline only. Operate outdoors in ventilated area.', 'rating' => 5.0, 'reviews' => 25,
            ],
            [
                'owner' => 'Sam', 'category' => 'Events', 'area' => 'Sherwood Park', 'city' => 'Strathcona County', 'prov' => 'AB',
                'lat' => 53.5430, 'lng' => -113.2930, 'address' => '100 Ordze Ave', 'postal' => 'T8A 2G3',
                'title' => 'Commercial 8oz Theater Popcorn Machine on Cart', 'brand' => 'Great Northern Popcorn', 'model' => 'Roosevelt 8oz', 'condition' => 'good', 'daily_rate' => 30,
                'description' => 'Nostalgic antique style popcorn maker with heated warming deck and large spoke wheels cart. Great for carnivals, school functions, and birthday parties.',
                'accessories' => ['Popcorn scoop', 'Kernel measuring cup', 'Oil scoop'], 'tags' => ['Popcorn', 'Kids Party', 'Carnival', 'Movie Night'],
                'rental_rules' => 'Wipe down kettle and glass panels after cooling down.', 'rating' => 4.8, 'reviews' => 15,
            ],

            // --- St. Albert ---
            [
                'owner' => 'Lucas', 'category' => 'Tools', 'area' => 'St. Albert', 'city' => 'St. Albert', 'prov' => 'AB',
                'lat' => 53.6305, 'lng' => -113.6256, 'address' => '5 St Anne St', 'postal' => 'T8N 1N2',
                'title' => 'Stihl 18" Gas Powered Chainsaw', 'brand' => 'Stihl', 'model' => 'MS 250', 'condition' => 'good', 'daily_rate' => 35,
                'description' => 'Powerful 45.4 cc mid-sized chainsaw with 18-inch bar. Great power-to-weight ratio for cutting firewood and storm cleanup.',
                'accessories' => ['Bar scabbard', 'Scrench tool', 'Safety face shield & ear muffs'], 'tags' => ['Chainsaw', 'Tree Cutting', 'Firewood', 'Yard Care'],
                'rental_rules' => 'Must use 50:1 2-stroke mixed fuel and bar oil. Safety chaps/face shield recommended.', 'rating' => 4.9, 'reviews' => 21,
            ],
            [
                'owner' => 'Lucas', 'category' => 'Outdoors', 'area' => 'St. Albert', 'city' => 'St. Albert', 'prov' => 'AB',
                'lat' => 53.6320, 'lng' => -113.6230, 'address' => '14 Mission Ave', 'postal' => 'T8N 1H4',
                'title' => 'Weber Q 1200 Portable Tabletop Propane Grill', 'brand' => 'Weber', 'model' => 'Q 1200', 'condition' => 'like_new', 'daily_rate' => 18,
                'description' => 'Cast-aluminium lid and porcelain-enameled cast-iron cooking grates with folding side work tables. Perfect for tailgate parties and lake trips.',
                'accessories' => ['Propane regulator', 'Grill spatula & tongs', 'Wire cleaning brush'], 'tags' => ['BBQ', 'Tailgating', 'Picnic', 'Grill'],
                'rental_rules' => 'Scrape cooking grates cleanly after cooking.', 'rating' => 4.8, 'reviews' => 14,
            ],

            // --- Clareview, Edmonton ---
            [
                'owner' => 'Chloe', 'category' => 'Tools', 'area' => 'Clareview', 'city' => 'Edmonton', 'prov' => 'AB',
                'lat' => 53.6050, 'lng' => -113.4100, 'address' => '4204 139 Ave NW', 'postal' => 'T5Y 2L4',
                'title' => 'DeWalt 10" Wet Tile Saw with Stand', 'brand' => 'DeWalt', 'model' => 'D24000S', 'condition' => 'good', 'daily_rate' => 32,
                'description' => 'Cantilevering rail / cart system allows 24" rip cut capacity and 18" diagonal tile cuts. Integrated water nozzles prevent spray.',
                'accessories' => ['Continuous rim diamond blade', 'Submersible water pump', 'Folding stand'], 'tags' => ['Tile Saw', 'Bathroom Remodel', 'Flooring'],
                'rental_rules' => 'Fill water reservoir before starting pump. Clean water tray after cutting.', 'rating' => 4.9, 'reviews' => 8,
            ],

            // ==================== INDORE NEIGHBOURHOODS ====================
            // --- Vijay Nagar, Indore ---
            [
                'owner' => 'Rahul', 'category' => 'Appliances', 'area' => 'Vijay Nagar', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.7533, 'lng' => 75.8937, 'address' => 'Near Bombay Hospital, Ring Road', 'postal' => '452010',
                'title' => 'Voltas 1 Ton Portable Air Conditioner', 'brand' => 'Voltas', 'model' => 'V-Cool 12', 'condition' => 'like_new', 'daily_rate' => 25,
                'description' => 'Fast-cooling mobile portable AC on castor wheels. Includes window exhaust pipe kit. Instant cooling for guest rooms or home office.',
                'accessories' => ['Exhaust hose pipe', 'Window slider bracket', 'Remote controller'], 'tags' => ['Portable AC', 'Cooling', 'Summer', 'Indore Heat'],
                'rental_rules' => 'Keep vertical during transport. Empty condensation water tray regularly.', 'rating' => 4.9, 'reviews' => 15,
            ],
            [
                'owner' => 'Rahul', 'category' => 'Tools', 'area' => 'Vijay Nagar', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.7550, 'lng' => 75.8950, 'address' => 'Scheme 54, PU4 Commercial', 'postal' => '452010',
                'title' => 'Bosch GSB 500W Professional Impact Drill Set', 'brand' => 'Bosch', 'model' => 'GSB 500 RE', 'condition' => 'good', 'daily_rate' => 12,
                'description' => '500-watt robust electric hammer impact drill with 100-piece accessory kit for drilling in concrete, brick, wood, and metal.',
                'accessories' => ['Hard carry case', 'Keyed chuck key', 'Masonry & wood bits set', 'Hammer, pliers & screwdriver bits'], 'tags' => ['Drill', 'Home Improvement', 'Wall Mounting'],
                'rental_rules' => 'Return with all bits and accessories neatly organized in the toolbox.', 'rating' => 4.8, 'reviews' => 24,
            ],

            // --- Palasia, Indore ---
            [
                'owner' => 'Ananya', 'category' => 'Events', 'area' => 'Palasia', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.7244, 'lng' => 75.8839, 'address' => '56 Dukan Lane, New Palasia', 'postal' => '452001',
                'title' => '18" LED Ring Light with 7ft Heavy Duty Tripod', 'brand' => 'Digitek', 'model' => 'DRL-18H', 'condition' => 'like_new', 'daily_rate' => 10,
                'description' => 'Professional bi-color dimmable 18-inch ring light with smartphone and DSLR mounts. Essential for content creation, makeup tutorials, and weddings.',
                'accessories' => ['7ft metal tripod stand', 'Mobile holder bracket', 'Mirror accessory', 'Power adapter'], 'tags' => ['Ring Light', 'Reels', 'Photography', 'Vlog'],
                'rental_rules' => 'Tighten tripod locking collars gently. Do not over-torque knobs.', 'rating' => 5.0, 'reviews' => 28,
            ],
            [
                'owner' => 'Ananya', 'category' => 'Tools', 'area' => 'Palasia', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.7260, 'lng' => 75.8860, 'address' => 'Old Palasia Road near Manorama Ganj', 'postal' => '452001',
                'title' => 'Karcher K2 Compact High Pressure Washer', 'brand' => 'Karcher', 'model' => 'K2 Compact', 'condition' => 'good', 'daily_rate' => 16,
                'description' => 'German engineered pressure washer with 110 bar output. Easily cleans tile courtyards, two-wheelers, cars, and building facades.',
                'accessories' => ['Dirt Blaster lance', 'One-way spray lance', '4m high pressure hose'], 'tags' => ['Car Wash', 'Karcher', 'Deep Clean', 'Balcony'],
                'rental_rules' => 'Connect garden hose with clear water flow before switching on power.', 'rating' => 4.8, 'reviews' => 19,
            ],

            // --- Rajwada, Indore ---
            [
                'owner' => 'Amit', 'category' => 'Events', 'area' => 'Rajwada', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.7196, 'lng' => 75.8577, 'address' => 'Near Rajwada Palace, MG Road', 'postal' => '452002',
                'title' => 'Canon EOS 200D II DSLR Camera Kit', 'brand' => 'Canon', 'model' => 'EOS 200D Mark II', 'condition' => 'like_new', 'daily_rate' => 28,
                'description' => 'Lightweight 24.1 MP 4K dual pixel CMOS DSLR camera with rotatable touch screen. Features eye-detection autofocus and Wi-Fi transfer.',
                'accessories' => ['18-55mm IS STM Lens', '55-250mm Telephoto Zoom Lens', '2x batteries & charger', '64GB fast SD card', 'Camera bag'], 'tags' => ['DSLR', 'Photography', 'Wedding Shoot', 'Travel'],
                'rental_rules' => 'Keep lens caps and lens hoods on when not in use. Do not expose sensor to dust.', 'rating' => 4.9, 'reviews' => 35,
            ],

            // --- Bhawarkua, Indore ---
            [
                'owner' => 'Pooja', 'category' => 'Events', 'area' => 'Bhawarkua', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.6926, 'lng' => 75.8676, 'address' => 'Bhawarkua Square near University campus', 'postal' => '452014',
                'title' => 'Epson Full HD Portable Smart Projector', 'brand' => 'Epson', 'model' => 'EB-E01', 'condition' => 'good', 'daily_rate' => 20,
                'description' => '3300 lumens 3LCD projection technology delivering crisp colors and bright presentations even in daylight. HDMI and USB inputs.',
                'accessories' => ['HDMI cable', 'Power cord', 'VGA cable', 'Remote control', 'Carry bag'], 'tags' => ['Projector', 'College Project', 'Seminar', 'Movie'],
                'rental_rules' => 'Allow fan to cool down completely before unplugging.', 'rating' => 4.8, 'reviews' => 16,
            ],

            // --- Annapurna, Indore ---
            [
                'owner' => 'Rohan', 'category' => 'Outdoors', 'area' => 'Annapurna', 'city' => 'Indore', 'prov' => 'MP',
                'lat' => 22.6974, 'lng' => 75.8322, 'address' => 'Annapurna Road near Mandir', 'postal' => '452009',
                'title' => 'Firefox 21-Speed Alloy Mountain Bicycle', 'brand' => 'Firefox', 'model' => 'Target 29D', 'condition' => 'like_new', 'daily_rate' => 12,
                'description' => 'Lightweight alloy hardtail 29-inch MTB with Shimano 21-speed gears and dual disc brakes. Front suspension with lockout for city and trails.',
                'accessories' => ['Adjustable cycling helmet', 'Numbered combination cable lock', 'LED front & rear light'], 'tags' => ['Bicycle', 'Cycling', 'Fitness', 'Trail'],
                'rental_rules' => 'Lock frame to stationary anchor when parked outside.', 'rating' => 4.9, 'reviews' => 20,
            ],
        ];

        // 5. Insert all items
        $createdItems = [];
        foreach ($itemsCatalog as $itemData) {
            $owner = $users->get($itemData['owner']);
            $cat = $categories->get($itemData['category']);
            if (! $owner || ! $cat) {
                continue;
            }

            $item = Item::updateOrCreate(
                ['user_id' => $owner->id, 'title' => $itemData['title']],
                [
                    'category_id' => $cat->id,
                    'brand' => $itemData['brand'],
                    'model' => $itemData['model'],
                    'condition' => $itemData['condition'],
                    'daily_rate' => $itemData['daily_rate'],
                    'description' => $itemData['description'],
                    'accessories' => $itemData['accessories'] ?? [],
                    'tags' => $itemData['tags'] ?? [],
                    'rental_rules' => $itemData['rental_rules'] ?? null,
                    'area' => $itemData['area'],
                    'city' => $itemData['city'],
                    'province' => $itemData['prov'],
                    'pickup_address' => $itemData['address'],
                    'postal_code' => $itemData['postal'],
                    'latitude' => $itemData['lat'],
                    'longitude' => $itemData['lng'],
                    'min_days' => 1,
                    'max_days' => 14,
                    'pickup_window_start' => '10:00',
                    'pickup_window_end' => '16:00',
                ]
            );

            $item->forceFill([
                'status' => Item::STATUS_ACTIVE,
                'submitted_at' => now()->subWeeks(2),
                'approved_at' => now()->subWeeks(2),
                'rating_avg' => $itemData['rating'],
                'reviews_count' => $itemData['reviews'],
            ])->save();

            $createdItems[$itemData['title']] = $item;
        }

        // 6. Confirmed booking NR1042 on Maya's Portable Dryer (safe upsert)
        $dryer = $createdItems['Portable Dryer'] ?? null;
        $drill = $createdItems['Cordless Drill Driver Kit'] ?? null;
        $alex = $users->get('Alex');
        $maya = $users->get('Maya');

        if ($dryer && $drill && $alex && $maya) {
            try {
                $card = PaymentMethod::firstOrCreate(
                    ['user_id' => $alex->id, 'last4' => '4242'],
                    ['brand' => 'VISA', 'exp_month' => 8, 'exp_year' => 2028, 'is_default' => true]
                );

                $start = now()->addDays(5)->startOfDay();
                $booking = Booking::firstOrCreate(
                    ['code' => 'NR1042'],
                    [
                        'item_id' => $dryer->id, 'renter_id' => $alex->id, 'owner_id' => $maya->id,
                        'start_date' => $start, 'end_date' => $start->copy()->addDays(2), 'pickup_time' => '10:00', 'return_time' => '10:00', 'days' => 3,
                        'message' => "Hi Maya! I'd like to rent your dryer for a few days while mine is being repaired.",
                        'daily_rate' => 20, 'rental_subtotal' => 60, 'service_fee' => 6, 'delivery_fee' => 0, 'taxes' => 0, 'security_deposit' => 0, 'total' => 66,
                        'status' => Booking::CONFIRMED, 'cancellation_policy' => 'flexible',
                        'accepted_at' => now()->subDay(), 'paid_at' => now()->subHours(20),
                    ]
                );
                $booking->update(['item_id' => $dryer->id, 'owner_id' => $maya->id, 'renter_id' => $alex->id]);

                Payment::firstOrCreate(
                    ['booking_id' => $booking->id, 'type' => 'charge'],
                    ['user_id' => $alex->id, 'payment_method_id' => $card->id, 'status' => 'succeeded', 'amount' => 66, 'provider_reference' => 'ch_'.Str::random(20), 'description' => 'Booking NR1042']
                );

                Payout::firstOrCreate(
                    ['booking_id' => $booking->id],
                    ['owner_id' => $maya->id, 'amount' => 60, 'status' => Payout::PENDING]
                );

                // Pending request on Taylor's drill
                $taylor = $users->get('Taylor');
                $sam = $users->get('Sam');
                if ($taylor && $sam) {
                    Booking::firstOrCreate(
                        ['code' => 'NR20315'],
                        [
                            'item_id' => $drill->id, 'renter_id' => $sam->id, 'owner_id' => $taylor->id,
                            'start_date' => now()->addDays(10), 'end_date' => now()->addDays(10), 'days' => 1, 'message' => 'Hi! Is the drill still available this weekend?',
                            'daily_rate' => 15, 'rental_subtotal' => 15, 'service_fee' => 1.5, 'total' => 16.5, 'status' => Booking::REQUESTED,
                        ]
                    );
                }

                $messaging = app(MessagingService::class);
                $chat = $messaging->bookingConversation($booking);
                $messaging->post($chat, $maya, "Hi Alex! The portable dryer is ready for your booking. Would you like to pick it up at my place in Mill Woods?");
                $messaging->post($chat->load('participants'), $alex, "Hi Maya! Yes, I can pick it up around 10:00 AM. Is that time still good for you?");
                $messaging->post($chat->load('participants'), $maya, "Sounds good! I'll have the portable dryer ready for pickup.");
            } catch (\Throwable $e) {
                // Ignore if booking / conversation already initialized
            }

            try {
                $alex->favoriteItems()->syncWithoutDetaching([$dryer->id, $drill->id]);
            } catch (\Throwable $e) {}

            try {
                AppNotification::firstOrCreate(
                    ['user_id' => $alex->id, 'title' => 'Booking accepted'],
                    ['type' => 'booking_accepted', 'category' => 'bookings', 'body' => 'Portable Dryer â€¢ NR1042 â€¢ Owned by Maya', 'data' => ['booking_id' => $booking->id]]
                );
            } catch (\Throwable $e) {}
        }
    }
}
