<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;

/**
 * Abstraction over the card processor. Swap FakePaymentGateway for a Stripe implementation
 * (e.g. using stripe/stripe-php PaymentIntents) by binding it in AppServiceProvider.
 */
interface PaymentGateway
{
    /** @return array{success: bool, reference: ?string, error: ?string} */
    public function charge(PaymentMethod $method, float $amount, string $currency, string $description, array $options = []): array;

    /** @return array{success: bool, reference: ?string, error: ?string} */
    public function refund(?string $chargeReference, float $amount, string $currency): array;
}
