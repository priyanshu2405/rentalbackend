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
];


