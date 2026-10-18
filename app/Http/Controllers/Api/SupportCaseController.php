<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportCaseResource;
use App\Models\Booking;
use App\Models\SupportCase;
use App\Services\CodeGenerator;
use App\Services\MessagingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "Report damage or issue" + "Support Case" screens. */
class SupportCaseController extends Controller
{
    public function __construct(private MessagingService $messaging, private NotificationService $notifications) {}

    public function index(Request $request)
    {
        return SupportCaseResource::collection(
            SupportCase::where('user_id', $request->user()->id)->with(['booking.item', 'booking.renter', 'conversation'])->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'type' => ['required', 'in:'.implode(',', SupportCase::TYPES)],
            'subject' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:8192'],
        ]);
        $user = $request->user();

        if (! empty($data['booking_id'])) {
            abort_unless(Booking::findOrFail($data['booking_id'])->isParticipant($user), 403);
        }

        $case = DB::transaction(function () use ($request, $data, $user) {
            $case = SupportCase::create([
                'code' => CodeGenerator::unique(SupportCase::class, 'SUP', 4),
                'user_id' => $user->id,
                'booking_id' => $data['booking_id'] ?? null,
                'type' => $data['type'],
                'subject' => $data['subject'] ?? null,
                'description' => $data['description'],
                'photos' => [],
                'status' => SupportCase::SUBMITTED,
            ]);
            $case->update(['photos' => $this->storeUploads($request->file('photos', []), "support/{$case->id}")]);

            $conversation = $this->messaging->supportConversation($case);
            $this->messaging->post($conversation, $user, $data['description']);
            $this->messaging->post($conversation->load('participants'), null, "We've received your message and will get back to you shortly. Your case number is {$case->code}.");

            return $case;
        });

        return response()->json(['data' => new SupportCaseResource($case->load(['booking.item', 'booking.renter', 'conversation']))], 201);
    }

    public function show(Request $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($supportCase->user_id === $request->user()->id || $request->user()->is_admin, 403);

        return response()->json(['data' => new SupportCaseResource($supportCase->load(['booking.item', 'booking.renter', 'conversation', 'user']))]);
    }
}
