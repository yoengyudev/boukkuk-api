<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('payments')->delete();
        DB::table('wishlists')->delete();
        DB::table('carts')->delete();
        DB::table('services')->delete();
        DB::table('categories')->delete();
        DB::table('role_user')->delete();
        DB::table('roles')->delete();
        DB::table('users')->delete();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'User', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Provider', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $userId = $this->createUser('Demo Customer', 'user@boukuk.test', '010111222', 1, [
            'google_map_url' => 'https://maps.google.com/?q=Phnom+Penh',
            'latitude' => 11.5564,
            'longitude' => 104.9282,
        ]);
        $adminId = $this->createUser('Demo Admin', 'admin@boukuk.test', '010333444', 2);
        $providerId = $this->createUser('CleanPro Laundry', 'provider@boukuk.test', '010555666', 3, [
            'google_map_url' => 'https://maps.google.com/?q=Russian+Market+Phnom+Penh',
            'latitude' => 11.5412,
            'longitude' => 104.9144,
        ]);
        $providerTwoId = $this->createUser('Fresh Fold Studio', 'provider2@boukuk.test', '010777888', 3, [
            'google_map_url' => 'https://maps.google.com/?q=BKK1+Phnom+Penh',
            'latitude' => 11.5504,
            'longitude' => 104.9261,
        ]);

        $categories = [
            'Wash and Fold',
            'Dry Cleaning',
            'Ironing',
            'Delivery',
            'Blanket Cleaning',
        ];

        foreach ($categories as $name) {
            DB::table('categories')->insert([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $services = [
            [$providerId, 1, 'Daily Clothes Wash', 'Everyday laundry washed, dried, and folded neatly.', 6500, 0],
            [$providerId, 2, 'Suit Dry Cleaning', 'Careful dry cleaning for suits, blazers, and formal wear.', 18000, 10],
            [$providerId, 3, 'Office Shirt Ironing', 'Crisp ironing for shirts and office uniforms.', 2500, 0],
            [$providerId, 5, 'Blanket Deep Clean', 'Deep clean service for blankets and heavy bedding.', 22000, 5],
            [$providerTwoId, 1, 'Express Wash', 'Same-day wash and fold for small laundry loads.', 9000, 0],
            [$providerTwoId, 2, 'Dress Care', 'Gentle care for dresses and delicate fabric.', 16000, 0],
            [$providerTwoId, 3, 'Family Ironing Pack', 'Bulk ironing package for family clothes.', 12000, 15],
            [$providerTwoId, 4, 'Pickup and Delivery', 'Door-to-door laundry pickup and delivery.', 5000, 0],
        ];

        $serviceIds = [];
        foreach ($services as $index => [$creatorId, $categoryId, $name, $description, $price, $discount]) {
            $serviceIds[] = DB::table('services')->insertGetId([
                'creator_id' => $creatorId,
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'discount' => $discount,
                'image' => asset('demo/service-'.(($index % 4) + 1).'.svg'),
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
        }

        DB::table('wishlists')->insert([
            ['user_id' => $userId, 'service_id' => $serviceIds[0], 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userId, 'service_id' => $serviceIds[4], 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('carts')->insert([
            ['user_id' => $userId, 'service_id' => $serviceIds[1], 'qty' => 1, 'price' => 18000, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userId, 'service_id' => $serviceIds[2], 'qty' => 3, 'price' => 2500, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('payments')->insert([
            [
                'buyer_id' => $userId,
                'service_id' => $serviceIds[0],
                'qty' => 2,
                'price' => 13000,
                'payment_status' => 2,
                'service_status' => 8,
                'transaction_file' => asset('demo/transaction-placeholder.svg'),
                'pickup_schedule' => now()->addDay(),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(4),
            ],
            [
                'buyer_id' => $userId,
                'service_id' => $serviceIds[1],
                'qty' => 1,
                'price' => 18000,
                'payment_status' => 1,
                'service_status' => 2,
                'transaction_file' => asset('demo/transaction-placeholder.svg'),
                'pickup_schedule' => now()->addDays(2),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'buyer_id' => $userId,
                'service_id' => $serviceIds[5],
                'qty' => 1,
                'price' => 16000,
                'payment_status' => 3,
                'service_status' => 1,
                'transaction_file' => asset('demo/transaction-placeholder.svg'),
                'pickup_schedule' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
        ]);
    }

    private function createUser(string $name, string $email, string $phone, int $roleId, array $extra = []): int
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'avatar' => 'https://ui-avatars.com/api/?background=0D6EFD&color=fff&name='.urlencode($name),
            'password' => Hash::make('password'),
            'api_token' => Str::random(60),
            'is_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));

        DB::table('role_user')->insert([
            'user_id' => $id,
            'role_id' => $roleId,
        ]);

        return $id;
    }
}
