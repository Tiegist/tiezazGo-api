<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = SubscriptionPlan::query();

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'price', 'name'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Subscription plans retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['daily', 'monthly'])],
        ]);

        $plan = SubscriptionPlan::create($validated);

        return $this->respondSuccess($plan, 'Subscription plan created successfully', status: 201);
    }

    public function show(Request $request, SubscriptionPlan $subscriptionPlan): \Illuminate\Http\JsonResponse
    {
        $includes = $this->includes($request, ['subscriptions', 'payments']);
        if ($includes) {
            $subscriptionPlan->load($includes);
        }

        return $this->respondSuccess($subscriptionPlan, 'Subscription plan retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'billing_cycle' => ['sometimes', Rule::in(['daily', 'monthly'])],
        ]);

        $subscriptionPlan->update($validated);

        return $this->respondSuccess($subscriptionPlan->fresh(), 'Subscription plan updated successfully');
    }

    public function destroy(Request $request, SubscriptionPlan $subscriptionPlan): \Illuminate\Http\JsonResponse
    {
        $subscriptionPlan->delete();

        return $this->respondSuccess(null, 'Subscription plan deleted successfully');
    }
}
