<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Feedback;
use App\Models\MenuAvailableDate;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SaasPayment;
use App\Models\SubscriptionPlan;
use App\Models\Table;
use App\Models\User;
use App\Services\Qr\QrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SaasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $basicPlan = SubscriptionPlan::query()->firstOrCreate(
            ['name' => 'Basic'],
            ['price' => 19.99, 'billing_cycle' => 'monthly']
        );

        $proPlan = SubscriptionPlan::query()->firstOrCreate(
            ['name' => 'Pro'],
            ['price' => 49.99, 'billing_cycle' => 'monthly']
        );

        $restaurant = Restaurant::query()->firstOrCreate(
            ['slug' => 'tiezaz-demo'],
            [
                'name' => 'Tiezaz Demo Restaurant',
                'logo' => null,
                'description' => 'Demo data for QR menu SaaS.',
                'phone' => '+251900000000',
                'email' => 'demo@tiezaz.local',
                'address' => 'Addis Ababa',
                'google_maps' => null,
                'is_active' => true,
                'moto' => 'Scan. Order. Enjoy.',
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'admin@tiezaz.local'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => 'admin',
                'restaurant_id' => null,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'owner@tiezaz.local'],
            [
                'name' => 'Restaurant Owner',
                'password' => 'password',
                'role' => 'restaurant_owner',
                'restaurant_id' => $restaurant->id,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'staff@tiezaz.local'],
            [
                'name' => 'Staff User',
                'password' => 'password',
                'role' => 'staff',
                'restaurant_id' => $restaurant->id,
            ]
        );

        $subscription = RestaurantSubscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'subscription_plan_id' => $proPlan->id,
            'starts_at' => now()->subDays(3),
            'expires_at' => now()->addDays(27),
            'status' => 'active',
        ]);

        SaasPayment::query()->create([
            'restaurant_id' => $restaurant->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'payment_method' => 'manual',
            'transaction_reference' => Str::upper(Str::random(12)),
            'status' => 'successful',
            'paid_at' => now()->subDays(3),
        ]);

        $categories = collect([
            ['name' => 'Breakfast', 'description' => 'Morning favorites', 'display_order' => 1],
            ['name' => 'Lunch', 'description' => 'Popular lunch items', 'display_order' => 2],
            ['name' => 'Drinks', 'description' => 'Hot & cold beverages', 'display_order' => 3],
        ])->map(function (array $c) use ($restaurant) {
            return Category::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $c['name'],
                'description' => $c['description'],
                'display_order' => $c['display_order'],
            ]);
        });

        $menuItems = collect();

        foreach ($categories as $category) {
            $items = match ($category->name) {
                'Breakfast' => [
                    ['am' => 'ፉል', 'en' => 'Ful', 'price' => 3.50],
                    ['am' => 'እንቁላል', 'en' => 'Eggs', 'price' => 4.00],
                ],
                'Lunch' => [
                    ['am' => 'ዶሮ ወጥ', 'en' => 'Doro Wat', 'price' => 12.00],
                    ['am' => 'ሽሮ', 'en' => 'Shiro', 'price' => 7.50],
                ],
                default => [
                    ['am' => 'ቡና', 'en' => 'Coffee', 'price' => 2.00],
                    ['am' => 'ሻይ', 'en' => 'Tea', 'price' => 1.50],
                ],
            };

            foreach ($items as $i => $item) {
                $menuItem = MenuItem::query()->create([
                    'category_id' => $category->id,
                    'name' => ['am' => $item['am'], 'en' => $item['en']],
                    'description' => null,
                    'ingredients' => null,
                    'price' => $item['price'],
                    'image' => null,
                    'is_available' => true,
                    'display_order' => $i + 1,
                ]);

                $menuItems->push($menuItem);

                MenuAvailableDate::query()->create([
                    'menu_item_id' => $menuItem->id,
                    'day' => 'daily',
                    'time' => '08:00-20:00',
                    'description' => null,
                ]);
            }
        }

        foreach (range(1, 10) as $n) {
            $table = Table::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_number' => $n,
                'qr_code' => null,
                'is_active' => true,
            ]);

            // DatabaseSeeder uses WithoutModelEvents, so generate QR codes explicitly here.
            app(QrCodeService::class)->generateForTable($table);
        }

        foreach (range(1, 12) as $i) {
            Feedback::query()->create([
                'restaurant_id' => $restaurant->id,
                'menu_item_id' => $menuItems->random()->id,
                'customer_name' => "Customer {$i}",
                'rating' => rand(3, 5),
                'comment' => 'Great food and quick service.',
                'is_approved' => (bool) rand(0, 1),
            ]);
        }
    }
}

