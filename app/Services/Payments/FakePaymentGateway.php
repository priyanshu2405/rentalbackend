<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use Illuminate\Support\Str;

/**
 * Development gateway: always succeeds, except
 *  - when $options['simulate_failure'] is true (the "Simulate failed payment" button), or
 *  - for cards whose last4 is 0002 (Stripe's generic decline test card).
 */
class FakePaymentGateway implements PaymentGateway
{
    public function charge(PaymentMethod $method, float $amount, string $currency, string $description, array $options = []): array
    {
        if (! empty($options['simulate_failure']) || $method->last4 === '0002') {
            return ['success' => false, 'reference' => null, 'error' => 'Your card was declined.'];
        }

        $expired = now()->startOfMonth()->gt(now()->setDate($method->exp_year, $method->exp_month, 1));
        if ($expired) {
            return ['success' => false, 'reference' => null, 'error' => 'Your card has expired.'];
        }

        return ['success' => true, 'reference' => 'ch_'.Str::random(20), 'error' => null];
    }

    public function refund(?string $chargeReference, float $amount, string $currency): array
    {
        return ['success' => true, 'reference' => 're_'.Str::random(20), 'error' => null];
    }
}
