<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Restaurant::query();

        if (! $this->isAdmin($request)) {
            $rid = optional($request->user())->restaurant_id;
            $query->whereKey($rid ?: 0);
        }

        $includes = $this->includes($request, ['categories', 'tables', 'feedback']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'name', 'slug'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Restaurants retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('restaurants', 'slug')],
            'logo' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'google_maps' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'moto' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['slug'])) {
            $base = Str::slug($validated['name']);
            $slug = $base;
            $i = 2;
            while (Restaurant::query()->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $validated['slug'] = $slug;
        }

        $restaurant = Restaurant::create($validated);

        Cache::forget("public_menu:v1:{$restaurant->slug}");

        return $this->respondSuccess($restaurant, 'Restaurant created successfully', status: 201);
    }

    public function show(Request $request, Restaurant $restaurant): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = optional($request->user())->restaurant_id;
            if ((int) $rid !== (int) $restaurant->id) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['categories', 'tables', 'feedback']);
        if ($includes) {
            $restaurant->load($includes);
        }

        return $this->respondSuccess($restaurant, 'Restaurant retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, Restaurant $restaurant): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = optional($request->user())->restaurant_id;
            if ((int) $rid !== (int) $restaurant->id) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('restaurants', 'slug')->ignore($restaurant->id)],
            'logo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_maps' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'moto' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('slug', $validated) && empty($validated['slug'])) {
            $name = $validated['name'] ?? $restaurant->name;
            $base = Str::slug($name);
            $slug = $base;
            $i = 2;
            while (Restaurant::query()->where('slug', $slug)->whereKeyNot($restaurant->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $validated['slug'] = $slug;
        }

        $restaurant->update($validated);

        $restaurant = $restaurant->fresh();
        Cache::forget("public_menu:v1:{$restaurant->slug}");

        return $this->respondSuccess($restaurant, 'Restaurant updated successfully');
    }

    public function destroy(Request $request, Restaurant $restaurant): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            $rid = optional($request->user())->restaurant_id;
            if ((int) $rid !== (int) $restaurant->id) {
                abort(404);
            }
        }

        Cache::forget("public_menu:v1:{$restaurant->slug}");
        $restaurant->delete();

        return $this->respondSuccess(null, 'Restaurant deleted successfully');
    }
}
