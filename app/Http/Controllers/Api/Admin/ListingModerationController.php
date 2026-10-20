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

    /** ?status=pending (default) | active | paused | rejected | draft | all  &search=  &category= */
    public function index(Request $request)
    {
        $status = $request->input('status', Item::STATUS_PENDING);

        return ItemResource::collection(
            Item::with(['photos', 'category', 'owner'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->input('search'), function ($q, $s) {
                    $term = "%{$s}%";
                    $q->where(fn ($w) => $w->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('owner', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
                })
                ->when($request->input('category'), fn ($q, $c) => $q->where('category_id', $c))
                ->orderByRaw('submitted_at IS NULL, submitted_at ASC')
                ->latest('id')
                ->paginate((int) $request->input('per_page', 30))
        );
    }

    /** Admin can take down a live listing (e.g. reported content). */
    public function pause(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $item->forceFill(['status' => Item::STATUS_PAUSED, 'rejection_reason' => $data['reason']])->save();

        $this->notifications->send($item->owner, 'listing_paused', 'updates', 'Listing paused by Neighbours Rental',
            "{$item->title}: {$data['reason']}", ['item_id' => $item->id]);

        return response()->json(['data' => new ItemResource($item->load(['photos', 'category', 'owner']))]);
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
