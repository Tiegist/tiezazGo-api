<?php

namespace App\Http\Controllers;

use App\Models\SaasPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaasPaymentController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = SaasPayment::query();

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

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'paid_at', 'amount'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'SaaS payments retrieved successfully', [
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
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['pending', 'successful', 'failed'])],
            'paid_at' => ['nullable', 'date'],
        ]);

        if (! $this->isAdmin($request)) {
            $validated['restaurant_id'] = $this->requireRestaurantId($request);
        }

        $payment = SaasPayment::create($validated);

        return $this->respondSuccess($payment, 'SaaS payment created successfully', status: 201);
    }

    public function show(Request $request, SaasPayment $saasPayment): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $saasPayment->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['restaurant', 'subscriptionPlan']);
        if ($includes) {
            $saasPayment->load($includes);
        }

        return $this->respondSuccess($saasPayment, 'SaaS payment retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, SaasPayment $saasPayment): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $saasPayment->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'sometimes' : 'prohibited', 'integer', 'exists:restaurants,id'],
            'subscription_plan_id' => ['sometimes', 'integer', 'exists:subscription_plans,id'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'string', 'max:255'],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['pending', 'successful', 'failed'])],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $saasPayment->update($validated);

        return $this->respondSuccess($saasPayment->fresh(), 'SaaS payment updated successfully');
    }

    public function destroy(Request $request, SaasPayment $saasPayment): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $saasPayment->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $saasPayment->delete();

        return $this->respondSuccess(null, 'SaaS payment deleted successfully');
    }
}
