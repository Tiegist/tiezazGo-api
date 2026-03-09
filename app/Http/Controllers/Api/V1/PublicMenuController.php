<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicMenuController extends Controller
{
    private function menuCacheKey(string $restaurantSlug): string
    {
        return "public_menu:v1:{$restaurantSlug}";
    }

    private function getMenu(string $restaurantSlug): array
    {
        $cacheKey = $this->menuCacheKey($restaurantSlug);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($restaurantSlug) {
            $restaurant = Restaurant::query()
                ->where('slug', $restaurantSlug)
                ->where('is_active', true)
                ->with([
                    'categories' => function ($q) {
                        $q->orderBy('display_order')
                            ->with([
                                'menuItems' => function ($q) {
                                    $q->where('is_available', true)
                                        ->orderBy('display_order')
                                        ->with('availableDates');
                                },
                            ]);
                    },
                ])
                ->firstOrFail();

            return [
                'restaurant' => $restaurant,
                'categories' => $restaurant->categories,
            ];
        });
    }

    public function show(Request $request, string $restaurant_slug): \Illuminate\Http\JsonResponse
    {
        $cacheKey = $this->menuCacheKey($restaurant_slug);
        $cacheHit = Cache::has($cacheKey);
        $menu = $this->getMenu($restaurant_slug);

        return $this->respondSuccess($menu, 'Menu retrieved successfully', [
            'restaurant_slug' => $restaurant_slug,
            'cache' => (object) [
                'key' => $cacheKey,
                'hit' => $cacheHit,
                'ttl_seconds' => 300,
            ],
        ]);
    }

    public function categories(Request $request, string $restaurant_slug): \Illuminate\Http\JsonResponse
    {
        $restaurant = Restaurant::query()
            ->where('slug', $restaurant_slug)
            ->where('is_active', true)
            ->firstOrFail();

        $query = Category::query()
            ->where('restaurant_id', $restaurant->id);

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'display_order', 'name'], 'display_order', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Categories retrieved successfully', [
            'restaurant_slug' => $restaurant_slug,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ]);
    }

    public function items(Request $request, string $restaurant_slug): \Illuminate\Http\JsonResponse
    {
        $categoryId = $request->query('category_id');

        $restaurant = Restaurant::query()
            ->where('slug', $restaurant_slug)
            ->where('is_active', true)
            ->firstOrFail();

        $query = MenuItem::query()
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('restaurant_id', $restaurant->id));

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $includes = $this->includes($request, ['availableDates', 'category']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'display_order', 'price'], 'display_order', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Menu items retrieved successfully', [
            'restaurant_slug' => $restaurant_slug,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'category_id' => $categoryId,
            ],
            'include' => $includes,
        ]);
    }
}

