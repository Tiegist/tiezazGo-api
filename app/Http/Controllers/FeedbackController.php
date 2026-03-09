<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Feedback::query();

        if ($this->isAdmin($request)) {
            if ($request->filled('restaurant_id')) {
                $query->where('restaurant_id', $request->query('restaurant_id'));
            }
        } else {
            $query->where('restaurant_id', $this->requireRestaurantId($request));
        }

        if ($request->filled('menu_item_id')) {
            $query->where('menu_item_id', $request->query('menu_item_id'));
        }

        if ($request->filled('is_approved')) {
            $query->where('is_approved', filter_var($request->query('is_approved'), FILTER_VALIDATE_BOOLEAN));
        }

        $includes = $this->includes($request, ['restaurant', 'menuItem']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'rating'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Feedback retrieved successfully', [
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'filters' => (object) [
                'restaurant_id' => $request->query('restaurant_id'),
                'menu_item_id' => $request->query('menu_item_id'),
                'is_approved' => $request->query('is_approved'),
            ],
            'include' => $includes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'required' : 'sometimes', 'integer', 'exists:restaurants,id'],
            'menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        if (! $this->isAdmin($request)) {
            $validated['restaurant_id'] = $this->requireRestaurantId($request);
        }

        $feedback = Feedback::create($validated);

        return $this->respondSuccess($feedback, 'Feedback created successfully', status: 201);
    }

    public function show(Request $request, Feedback $feedback): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $feedback->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['restaurant', 'menuItem']);
        if ($includes) {
            $feedback->load($includes);
        }

        return $this->respondSuccess($feedback, 'Feedback retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, Feedback $feedback): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $feedback->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'sometimes' : 'prohibited', 'integer', 'exists:restaurants,id'],
            'menu_item_id' => ['sometimes', 'nullable', 'integer', 'exists:menu_items,id'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        $feedback->update($validated);

        return $this->respondSuccess($feedback->fresh(), 'Feedback updated successfully');
    }

    public function destroy(Request $request, Feedback $feedback): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $feedback->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $feedback->delete();

        return $this->respondSuccess(null, 'Feedback deleted successfully');
    }
}
