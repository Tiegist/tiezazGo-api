<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuItemController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = MenuItem::query();

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $query->whereHas('category', function ($q) use ($rid) {
                $q->where('restaurant_id', $rid);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $includes = $this->includes($request, ['category', 'availableDates']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'display_order', 'price'], 'display_order', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Menu items retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'category_id' => $request->query('category_id'),
            ],
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'array'],
            'name.am' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'array'],
            'is_available' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = Category::query()
                ->whereKey($validated['category_id'])
                ->where('restaurant_id', $rid)
                ->exists();
            if (! $allowed) {
                abort(403, 'Forbidden');
            }
        }

        $menuItem = MenuItem::create($validated);

        $menuItem->loadMissing('category.restaurant');
        if ($menuItem->category?->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$menuItem->category->restaurant->slug}");
        }

        return $this->respondSuccess($menuItem, 'Menu item created successfully', status: 201);
    }

    public function show(Request $request, MenuItem $menuItem): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = Category::query()
                ->whereKey($menuItem->category_id)
                ->where('restaurant_id', $rid)
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['category', 'availableDates']);
        if ($includes) {
            $menuItem->load($includes);
        }

        return $this->respondSuccess($menuItem, 'Menu item retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, MenuItem $menuItem): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'array'],
            'name.am' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'ingredients' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['sometimes', 'nullable', 'array'],
            'is_available' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $categoryId = $validated['category_id'] ?? $menuItem->category_id;
            $allowed = Category::query()
                ->whereKey($categoryId)
                ->where('restaurant_id', $rid)
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $menuItem->update($validated);

        $menuItem = $menuItem->fresh()->loadMissing('category.restaurant');
        if ($menuItem->category?->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$menuItem->category->restaurant->slug}");
        }

        return $this->respondSuccess($menuItem, 'Menu item updated successfully');
    }

    public function destroy(Request $request, MenuItem $menuItem): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = Category::query()
                ->whereKey($menuItem->category_id)
                ->where('restaurant_id', $rid)
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $menuItem->loadMissing('category.restaurant');
        if ($menuItem->category?->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$menuItem->category->restaurant->slug}");
        }

        $menuItem->delete();

        return $this->respondSuccess(null, 'Menu item deleted successfully');
    }
}
