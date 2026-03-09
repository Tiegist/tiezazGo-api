<?php

namespace App\Http\Controllers;

use App\Models\RestaurantSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantSubscriptionController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = RestaurantSubscription::query();

        if ($this->isAdmin($request)) {
            if ($request->filled('restaurant_id')) {
                $query->where('restaurant_id', $request->query('restaurant_id'));
            }
        } else {
            $query->where('restaurant_id', $this->requireRestaurantId($request));
        }

        if ($request->filled('subscription_plan_id')) {
            $query->where('subscription_plan_id', $request->query('subscription_plan_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $includes = $this->includes($request, ['restaurant', 'subscriptionPlan']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'starts_at', 'expires_at'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Restaurant subscriptions retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'restaurant_id' => $request->query('restaurant_id'),
                'subscription_plan_id' => $request->query('subscription_plan_id'),
                'status' => $request->query('status'),
            ],
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'required' : 'sometimes', 'integer', 'exists:restaurants,id'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', Rule::in(['active', 'expired', 'cancelled'])],
        ]);

        if (! $this->isAdmin($request)) {
            $validated['restaurant_id'] = $this->requireRestaurantId($request);
        }

        $subscription = RestaurantSubscription::create($validated);

        return $this->respondSuccess($subscription, 'Restaurant subscription created successfully', status: 201);
    }

    public function show(Request $request, RestaurantSubscription $restaurantSubscription): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $restaurantSubscription->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['restaurant', 'subscriptionPlan']);
        if ($includes) {
            $restaurantSubscription->load($includes);
        }

        return $this->respondSuccess($restaurantSubscription, 'Restaurant subscription retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, RestaurantSubscription $restaurantSubscription): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $restaurantSubscription->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'sometimes' : 'prohibited', 'integer', 'exists:restaurants,id'],
            'subscription_plan_id' => ['sometimes', 'integer', 'exists:subscription_plans,id'],
            'starts_at' => ['sometimes', 'date'],
            'expires_at' => ['sometimes', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', Rule::in(['active', 'expired', 'cancelled'])],
        ]);

        $restaurantSubscription->update($validated);

        return $this->respondSuccess($restaurantSubscription->fresh(), 'Restaurant subscription updated successfully');
    }

    public function destroy(Request $request, RestaurantSubscription $restaurantSubscription): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $restaurantSubscription->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $restaurantSubscription->delete();

        return $this->respondSuccess(null, 'Restaurant subscription deleted successfully');
    }
}
