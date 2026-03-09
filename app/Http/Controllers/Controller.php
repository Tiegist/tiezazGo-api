<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function respondSuccess(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    protected function respondPaginated(LengthAwarePaginator $paginator, string $message = 'OK', array $meta = []): JsonResponse
    {
        $meta = array_merge($meta, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ]);

        return $this->respondSuccess($paginator->items(), $message, $meta);
    }

    protected function respondError(string $message = 'Error', array $errors = [], int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => (object) array_merge($meta, [
                'errors' => (object) $errors,
            ]),
        ], $status);
    }

    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);
        return max(1, min($max, $perPage));
    }

    /**
     * @param  array<int,string>  $allowed
     * @return array<int,string>
     */
    protected function includes(Request $request, array $allowed): array
    {
        $include = (string) $request->query('include', '');
        if ($include === '') {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $include))));
        return array_values(array_intersect($parts, $allowed));
    }

    protected function sortParams(Request $request, array $allowed, string $defaultBy = 'created_at', string $defaultDir = 'desc'): array
    {
        $sortBy = (string) $request->query('sort_by', $defaultBy);
        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = $defaultBy;
        }

        $sortDir = strtolower((string) $request->query('sort_dir', $defaultDir));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = $defaultDir;
        }

        return [$sortBy, $sortDir];
    }

    protected function isAdmin(Request $request): bool
    {
        return (string) optional($request->user())->role === 'admin';
    }

    protected function requireRestaurantId(Request $request): int
    {
        $rid = optional($request->user())->restaurant_id;
        if (! $rid) {
            abort(403, 'Restaurant context required');
        }
        return (int) $rid;
    }
}
