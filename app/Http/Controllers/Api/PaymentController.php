<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Payments screen: payment methods + billing history. */
class PaymentController extends Controller
{
    public function methods(Request $request)
    {
        return PaymentMethodResource::collection(
            $request->user()->paymentMethods()->orderByDesc('is_default')->latest()->get()
        );
    }

    /**
     * Add a card. In production the app should tokenise the card with Stripe (or similar) and send
     * only `provider_token` + display data. Never send or store full card numbers.
     */
    public function addMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_token' => ['nullable', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:20'],
            'last4' => ['required', 'digits:4'],
            'exp_month' => ['required', 'integer', 'between:1,12'],
            'exp_year' => ['required', 'integer', 'min:'.now()->year, 'max:'.(now()->year + 20)],
            'make_default' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        if ($data['exp_year'] == now()->year && $data['exp_month'] < now()->month) {
            throw ValidationException::withMessages(['exp_month' => 'This card has expired.']);
        }

        $method = DB::transaction(function () use ($user, $data) {
            $isDefault = ($data['make_default'] ?? false) || ! $user->paymentMethods()->exists();
            if ($isDefault) {
                $user->paymentMethods()->update(['is_default' => false]);
            }

            return $user->paymentMethods()->create([
                'provider' => $data['provider_token'] ? 'stripe' : 'fake',
                'provider_token' => $data['provider_token'] ?? null,
                'brand' => strtoupper($data['brand']),
                'last4' => $data['last4'],
                'exp_month' => $data['exp_month'],
                'exp_year' => $data['exp_year'],
                'is_default' => $isDefault,
            ]);
        });

        return response()->json(['data' => new PaymentMethodResource($method)], 201);
    }

    public function makeDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($request, $paymentMethod) {
            $request->user()->paymentMethods()->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);
        });

        return response()->json(['data' => new PaymentMethodResource($paymentMethod->fresh())]);
    }

    public function removeMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $user = $request->user();
        abort_unless($paymentMethod->user_id === $user->id, 404);

        $awaitingPayment = Booking::where('renter_id', $user->id)->whereIn('status', [Booking::CONFIRMED, Booking::ACTIVE])->exists();
        if ($awaitingPayment && $user->paymentMethods()->count() === 1) {
            throw ValidationException::withMessages(['payment_method' => 'Keep at least one card while you have an active rental (needed for extensions).']);
        }

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();
        if ($wasDefault) {
            $user->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Payment method removed.']);
    }

    /** Billing history: receipts for your past rentals (charges + refunds). */
    public function history(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['booking.item', 'booking.owner', 'paymentMethod'])
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return PaymentResource::collection($payments);
    }
}
