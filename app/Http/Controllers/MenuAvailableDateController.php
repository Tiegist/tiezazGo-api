<?php

namespace App\Http\Controllers;

use App\Models\MenuAvailableDate;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuAvailableDateController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = MenuAvailableDate::query();

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $query->whereHas('menuItem.category', function ($q) use ($rid) {
                $q->where('restaurant_id', $rid);
            });
        }

        if ($request->filled('menu_item_id')) {
            $query->where('menu_item_id', $request->query('menu_item_id'));
        }

        $includes = $this->includes($request, ['menuItem']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Menu available dates retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'menu_item_id' => $request->query('menu_item_id'),
            ],
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'day' => ['required', 'string', 'max:255'],
            'time' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = MenuItem::query()
                ->whereKey($validated['menu_item_id'])
                ->whereHas('category', fn ($q) => $q->where('restaurant_id', $rid))
                ->exists();
            if (! $allowed) {
                abort(403, 'Forbidden');
            }
        }

        $date = MenuAvailableDate::create($validated);

        $date->loadMissing('menuItem.category.restaurant');
        $slug = $date->menuItem?->category?->restaurant?->slug;
        if ($slug) {
            Cache::forget("public_menu:v1:{$slug}");
        }

        return $this->respondSuccess($date, 'Menu available date created successfully', status: 201);
    }

    public function show(Request $request, MenuAvailableDate $menuAvailableDate): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = MenuItem::query()
                ->whereKey($menuAvailableDate->menu_item_id)
                ->whereHas('category', fn ($q) => $q->where('restaurant_id', $rid))
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['menuItem']);
        if ($includes) {
            $menuAvailableDate->load($includes);
        }

        return $this->respondSuccess($menuAvailableDate, 'Menu available date retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, MenuAvailableDate $menuAvailableDate): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['sometimes', 'integer', 'exists:menu_items,id'],
            'day' => ['sometimes', 'string', 'max:255'],
            'time' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $menuItemId = $validated['menu_item_id'] ?? $menuAvailableDate->menu_item_id;
            $allowed = MenuItem::query()
                ->whereKey($menuItemId)
                ->whereHas('category', fn ($q) => $q->where('restaurant_id', $rid))
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $menuAvailableDate->update($validated);

        $menuAvailableDate = $menuAvailableDate->fresh()->loadMissing('menuItem.category.restaurant');
        $slug = $menuAvailableDate->menuItem?->category?->restaurant?->slug;
        if ($slug) {
            Cache::forget("public_menu:v1:{$slug}");
        }

        return $this->respondSuccess($menuAvailableDate, 'Menu available date updated successfully');
    }

    public function destroy(Request $request, MenuAvailableDate $menuAvailableDate): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = $this->requireRestaurantId($request);
            $allowed = MenuItem::query()
                ->whereKey($menuAvailableDate->menu_item_id)
                ->whereHas('category', fn ($q) => $q->where('restaurant_id', $rid))
                ->exists();
            if (! $allowed) {
                abort(404);
            }
        }

        $menuAvailableDate->loadMissing('menuItem.category.restaurant');
        $slug = $menuAvailableDate->menuItem?->category?->restaurant?->slug;
        if ($slug) {
            Cache::forget("public_menu:v1:{$slug}");
        }

        $menuAvailableDate->delete();

        return $this->respondSuccess(null, 'Menu available date deleted successfully');
    }
}
