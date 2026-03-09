<?php

namespace App\Observers;

use App\Models\Restaurant;
use App\Services\Qr\QrCodeService;

class RestaurantObserver
{
    public function updated(Restaurant $restaurant): void
    {
        if ($restaurant->wasChanged('slug')) {
            app(QrCodeService::class)->regenerateForRestaurant($restaurant);
        }
    }
}

