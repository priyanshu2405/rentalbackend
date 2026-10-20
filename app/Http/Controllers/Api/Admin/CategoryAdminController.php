<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryAdminController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(Category::withCount('items')->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:categories,name'],
            'icon' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) Category::max('sort_order') + 1),
        ]);

        return response()->json(['data' => new CategoryResource($category->loadCount('items'))], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', Rule::unique('categories', 'name')->ignore($category->id)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category->update($data);

        return response()->json(['data' => new CategoryResource($category->loadCount('items'))]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->items()->exists()) {
            throw ValidationException::withMessages(['category' => 'Move or delete the listings in this category first.']);
        }
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
