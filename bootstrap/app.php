<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => null,
                'meta' => (object) [
                    'errors' => (object) $e->errors(),
                ],
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => null,
                'meta' => (object) [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Forbidden',
                'data' => null,
                'meta' => (object) [],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Not found',
                'data' => null,
                'meta' => (object) [],
            ], 404);
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'data' => null,
                'meta' => (object) [],
            ], 500);
        });
    })->create();
