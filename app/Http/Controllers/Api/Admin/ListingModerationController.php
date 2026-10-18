<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ListingModerationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        return ItemResource::collection(
            Item::with(['photos', 'category', 'owner'])
                ->where('status', $request->input('status', Item::STATUS_PENDING))
                ->oldest('submitted_at')
                ->paginate(30)
        );
    }

    public function approve(Item $item): JsonResponse
    {
        if ($item->status !== Item::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending listings can be approved.']);
        }
        $item->forceFill(['status' => Item::STATUS_ACTIVE, 'approved_at' => now(), 'rejection_reason' => null])->save();

        $this->notifications->send($item->owner, 'listing_approved', 'updates', 'Your listing is live',
            "{$item->title} was approved and is now visible to neighbours.", ['item_id' => $item->id]);

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category', 'owner']))]);
    }

    public function reject(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $item->forceFill(['status' => Item::STATUS_REJECTED, 'rejection_reason' => $data['reason']])->save();

        $this->notifications->send($item->owner, 'listing_rejected', 'updates', 'Listing needs changes',
            "{$item->title}: {$data['reason']}", ['item_id' => $item->id]);

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category', 'owner']))]);
    }
}
