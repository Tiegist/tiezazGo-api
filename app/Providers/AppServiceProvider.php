<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Models\Table;
use App\Observers\RestaurantObserver;
use App\Observers\TableObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
     
        Schema::defaultStringLength(191);

        Table::observe(TableObserver::class);
        Restaurant::observe(RestaurantObserver::class);
    }
}
