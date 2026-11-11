<?php

return [
    'currency' => env('NR_CURRENCY', 'CAD'),

    // Platform service fee charged to the renter, as a percent of the rental subtotal.
    'service_fee_percent' => (float) env('NR_SERVICE_FEE_PERCENT', 10),

    // Sales tax percent applied to rental + service + delivery (0 in the designs).
    'tax_percent' => (float) env('NR_TAX_PERCENT', 0),

    // Hours a renter has to pay after the owner accepts before the dates are released.
    'payment_window_hours' => (int) env('NR_PAYMENT_WINDOW_HOURS', 48),

    // Days after the owner confirms the return before the payout becomes withdrawable.
    'payout_delay_days' => (int) env('NR_PAYOUT_DELAY_DAYS', 1),

    // When false, submitted listings wait for an admin ("Under review" → "Approved").
    'auto_approve_listings' => filter_var(env('NR_AUTO_APPROVE_LISTINGS', false), FILTER_VALIDATE_BOOLEAN),

    'verification_code_ttl_minutes' => (int) env('NR_VERIFICATION_CODE_TTL_MINUTES', 15),

    'max_item_photos' => 10,
    'max_check_photos' => 5,

    // Default search centre when neither the request nor the user supplies a location (Edmonton, AB).
    'default_location' => ['lat' => (float) env('DEFAULT_LAT', 53.5461), 'lng' => (float) env('DEFAULT_LNG', -113.4938), 'label' => env('DEFAULT_LOCATION_LABEL', 'Edmonton, AB')],
    'default_radius_km' => 10,

    'google_client_ids' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', '')))),

    'conditions' => ['like_new', 'good', 'fair', 'well_used'],

    'cancel_reasons' => [
        'My plans have changed',
        'Found another item',
        'Dates no longer work',
        'Other',
    ],

    /*
     | Cancellation policies (renter cancels a paid booking).
     | "flexible": full refund of everything until the rental start time.
     | "moderate": full refund minus service fee up to 7 days before, 50% of rental 1–6 days before,
     |             nothing in the last 24 hours. Security deposits are always returned before pickup.
     */
    'cancellation_policies' => ['flexible', 'moderate'],

    // Default legal copy returned until an admin updates them via /admin/legal.
    'terms_of_service' => <<<'TEXT'
Welcome to Neighbours Rental. By creating an account or using the platform, you agree to these Terms of Service.

1. Community Marketplace
Neighbours Rental connects people who want to rent everyday items from neighbours in their community. Owners are responsible for listing accurate item details and ensuring items are safe, clean, and in working condition.

2. Rentals & Returns
Renters agree to use rented items responsibly, follow any rental rules provided by the owner, and return items on time and in the same condition.

3. Payments & Fees
Payments are processed through Neighbours Rental and held until the rental is completed. Service fees and applicable cancellation policies are shown before checkout.

4. Disputes & Support
If an item is damaged, missing, or not as described, either party can report an issue through Help & support for review.
TEXT,

    'privacy_policy' => <<<'TEXT'
Neighbours Rental respects your privacy and explains below how your information is handled.

1. Information We Collect
We collect the profile details you provide (such as your name, email address, phone number, and profile photo) along with listing, booking, and message activity.

2. Location Information
We use your approximate location to show nearby items and search radiuses. Exact pickup addresses are only shared with a renter after a booking is confirmed and paid.

3. Payment Security
Card numbers are tokenized by the payment gateway; Neighbours Rental stores only the card brand, expiry date, and last 4 digits.

4. Your Controls
You can update your profile, notification preferences, and location settings at any time, or permanently delete your account from Settings.
TEXT,
];


