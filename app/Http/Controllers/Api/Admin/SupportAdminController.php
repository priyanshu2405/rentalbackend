<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportCaseResource;
use App\Models\SupportCase;
use App\Services\BookingService;
use App\Services\MessagingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupportAdminController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private MessagingService $messaging,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        return SupportCaseResource::collection(
            SupportCase::with(['booking.item', 'booking.renter', 'user', 'conversation'])
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(30)
        );
    }

    /** Move a case to reviewing / resolved, optionally issuing a refund to the renter. */
    public function update(Request $request, SupportCase $supportCase): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewing,resolved'],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === SupportCase::REVIEWING && ! $supportCase->reviewing_at) {
            $updates['reviewing_at'] = now();
        }
        if ($data['status'] === SupportCase::RESOLVED) {
            $updates['resolved_at'] = now();
            $updates['resolution'] = $data['resolution'] ?? $supportCase->resolution;
        }

        $refund = (float) ($data['refund_amount'] ?? 0);
        if ($refund > 0) {
            $booking = $supportCase->booking;
            if (! $booking || ! $booking->paid_at) {
                throw ValidationException::withMessages(['refund_amount' => 'Refunds need a paid booking.']);
            }
            $alreadyRefunded = (float) $booking->payments()->where('type', 'refund')->where('status', 'succeeded')->sum('amount');
            if ($refund + $alreadyRefunded > $booking->total) {
                throw ValidationException::withMessages(['refund_amount' => 'Refund exceeds the amount paid.']);
            }
            $this->bookings->refund($booking, $refund, "Support case {$supportCase->code}");
            $booking->increment('refund_amount', $refund);
            $updates['refund_amount'] = $supportCase->refund_amount + $refund;
            $updates['refund_status'] = 'processing';
        }

        $supportCase->update($updates);

        $conversation = $this->messaging->supportConversation($supportCase);
        if (! empty($data['message'])) {
            $conversation->participants()->syncWithoutDetaching([$request->user()->id]);
            $this->messaging->post($conversation->load('participants'), $request->user(), $data['message']);
        }

        $this->notifications->send($supportCase->user, 'support_case_updated', 'updates',
            "Support case {$supportCase->code}: ".ucfirst($data['status']),
            $refund > 0 ? 'A refund of C$'.number_format($refund, 2).' has been initiated.' : ($data['resolution'] ?? null),
            ['support_case_id' => $supportCase->id]);

        return response()->json(['data' => new SupportCaseResource($supportCase->fresh()->load(['booking.item', 'booking.renter', 'user', 'conversation']))]);
    }
}
