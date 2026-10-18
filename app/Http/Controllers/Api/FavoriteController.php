<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->favoriteItems()
            ->with(['photos', 'category', 'owner'])
            ->orderByPivot('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 20));

        $items->getCollection()->each(fn ($i) => $i->setAttribute('is_favorite', true));

        return ItemResource::collection($items);
    }

    public function store(Request $request, Item $item): JsonResponse
    {
        $request->user()->favoriteItems()->syncWithoutDetaching([$item->id]);

        return response()->json(['is_favorite' => true]);
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        $request->user()->favoriteItems()->detach($item->id);

        return response()->json(['is_favorite' => false]);
    }
}
