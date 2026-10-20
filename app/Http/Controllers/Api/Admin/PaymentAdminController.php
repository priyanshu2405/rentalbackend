<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:charge,refund'],
            'status' => ['nullable', 'in:succeeded,failed,pending'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $payments = Payment::with(['booking.item', 'booking.owner', 'paymentMethod'])
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->whereHas('booking', fn ($b) => $b->where('code', 'like', "%{$s}%")))
            ->latest()
            ->paginate(30);

        return PaymentResource::collection($payments);
    }
}
