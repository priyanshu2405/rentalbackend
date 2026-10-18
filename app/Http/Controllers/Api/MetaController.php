<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\SupportCase;
use Illuminate\Http\JsonResponse;

/** Lookup data the app needs on launch (categories, dropdown values, fees). */
class MetaController extends Controller
{
    public function categories()
    {
        return CategoryResource::collection(
            Category::withCount(['items' => fn ($q) => $q->where('status', 'active')])->orderBy('sort_order')->get()
        );
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'currency' => config('neighbours.currency'),
            'service_fee_percent' => config('neighbours.service_fee_percent'),
            'tax_percent' => config('neighbours.tax_percent'),
            'conditions' => config('neighbours.conditions'),
            'cancellation_policies' => config('neighbours.cancellation_policies'),
            'cancel_reasons' => config('neighbours.cancel_reasons'),
            'issue_types' => SupportCase::TYPES,
            'max_item_photos' => config('neighbours.max_item_photos'),
            'default_location' => config('neighbours.default_location'),
            'categories' => CategoryResource::collection(Category::orderBy('sort_order')->get()),
        ]);
    }
}
