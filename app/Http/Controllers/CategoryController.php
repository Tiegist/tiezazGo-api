<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Category::query();

        if ($this->isAdmin($request)) {
            if ($request->filled('restaurant_id')) {
                $query->where('restaurant_id', $request->query('restaurant_id'));
            }
        } else {
            $query->where('restaurant_id', $this->requireRestaurantId($request));
        }

        $includes = $this->includes($request, ['restaurant', 'menuItems']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'display_order', 'name'], 'display_order', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Categories retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'restaurant_id' => $request->query('restaurant_id'),
            ],
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'required' : 'sometimes', 'integer', 'exists:restaurants,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! $this->isAdmin($request)) {
            $validated['restaurant_id'] = $this->requireRestaurantId($request);
        }

        $category = Category::create($validated);

        $category->loadMissing('restaurant');
        if ($category->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$category->restaurant->slug}");
        }

        return $this->respondSuccess($category, 'Category created successfully', status: 201);
    }

    public function show(Request $request, Category $category): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $category->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['restaurant', 'menuItems']);
        if ($includes) {
            $category->load($includes);
        }

        return $this->respondSuccess($category, 'Category retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, Category $category): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $category->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'sometimes' : 'prohibited', 'integer', 'exists:restaurants,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $category->update($validated);

        $category = $category->fresh()->loadMissing('restaurant');
        if ($category->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$category->restaurant->slug}");
        }

        return $this->respondSuccess($category, 'Category updated successfully');
    }

    public function destroy(Request $request, Category $category): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $category->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $category->loadMissing('restaurant');
        if ($category->restaurant?->slug) {
            Cache::forget("public_menu:v1:{$category->restaurant->slug}");
        }

        $category->delete();

        return $this->respondSuccess(null, 'Category deleted successfully');
    }
}
