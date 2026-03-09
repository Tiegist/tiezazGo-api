<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Table::query();

        if ($this->isAdmin($request)) {
            if ($request->filled('restaurant_id')) {
                $query->where('restaurant_id', $request->query('restaurant_id'));
            }
        } else {
            $query->where('restaurant_id', $this->requireRestaurantId($request));
        }

        $includes = $this->includes($request, ['restaurant']);
        if ($includes) {
            $query->with($includes);
        }

        [$sortBy, $sortDir] = $this->sortParams($request, ['created_at', 'table_number'], 'created_at', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($this->perPage($request));

        return $this->respondPaginated($paginator, 'Tables retrieved successfully', [
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
            'table_number' => ['required', 'integer'],
            'qr_code' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! $this->isAdmin($request)) {
            $validated['restaurant_id'] = $this->requireRestaurantId($request);
        }

        $table = Table::create($validated);

        return $this->respondSuccess($table, 'Table created successfully', status: 201);
    }

    public function show(Request $request, Table $table): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $table->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $includes = $this->includes($request, ['restaurant']);
        if ($includes) {
            $table->load($includes);
        }

        return $this->respondSuccess($table, 'Table retrieved successfully', [
            'include' => $includes,
        ]);
    }

    public function update(Request $request, Table $table): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $table->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $validated = $request->validate([
            'restaurant_id' => [$this->isAdmin($request) ? 'sometimes' : 'prohibited', 'integer', 'exists:restaurants,id'],
            'table_number' => ['sometimes', 'integer'],
            'qr_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $table->update($validated);

        return $this->respondSuccess($table->fresh(), 'Table updated successfully');
    }

    public function destroy(Request $request, Table $table): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $table->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $table->delete();

        return $this->respondSuccess(null, 'Table deleted successfully');
    }
}
