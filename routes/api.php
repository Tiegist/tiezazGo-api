<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PublicMenuController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MenuAvailableDateController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RestaurantSubscriptionController;
use App\Http\Controllers\SaasPaymentController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
    });

    // Public QR-menu endpoint(s)
    Route::get('menu/{restaurant_slug}', [PublicMenuController::class, 'show']);
    Route::get('menu/{restaurant_slug}/categories', [PublicMenuController::class, 'categories']);
    Route::get('menu/{restaurant_slug}/items', [PublicMenuController::class, 'items']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);

        Route::middleware('role:admin,restaurant_owner')->group(function (): void {
            Route::apiResource('restaurants', RestaurantController::class);
        });

        Route::middleware('role:admin,restaurant_owner,staff')->group(function (): void {
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('menu-items', MenuItemController::class);
            Route::apiResource('menu-available-dates', MenuAvailableDateController::class);
            Route::apiResource('tables', TableController::class);
            Route::apiResource('feedback', FeedbackController::class);
            Route::apiResource('restaurant-subscriptions', RestaurantSubscriptionController::class);
            Route::apiResource('saas-payments', SaasPaymentController::class);
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::apiResource('subscription-plans', SubscriptionPlanController::class);
        });
    });
});

