<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoukKukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_contract_for_core_demo_flow(): void
    {
        $this->seed();

        $login = $this->postJson('/api/login', [
            'email_or_phone' => 'user@boukuk.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('data.roles.0.id', 1)
            ->json('data');

        $this->getJson('/api/services?page=1&per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'category' => ['id', 'name'], 'creator' => ['id', 'name', 'roles']],
                ],
                'paginate' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);

        $this->withHeader('Authorization', 'Bearer '.$login['token'])
            ->getJson('/api/profile/carts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => ['id', 'qty', 'price', 'service' => ['id', 'name', 'creator']],
                    ],
                    'total',
                ],
            ]);

        $provider = $this->postJson('/api/login', [
            'email_or_phone' => 'provider@boukuk.test',
            'password' => 'password',
        ])->json('data');

        $this->withHeader('Authorization', 'Bearer '.$provider['token'])
            ->getJson('/api/profile/payment-check?page=1&per_page=20&payment_status=')
            ->assertOk()
            ->assertJsonPath('paginate.total', 2);
    }

    public function test_cart_merges_duplicate_services_and_updates_quantity(): void
    {
        $this->seed();

        $login = $this->postJson('/api/login', [
            'email_or_phone' => 'user@boukuk.test',
            'password' => 'password',
        ])->json('data');

        $user = DB::table('users')->where('email', 'user@boukuk.test')->first();
        $service = DB::table('services')->first();
        DB::table('carts')->where('user_id', $user->id)->delete();

        $headers = ['Authorization' => 'Bearer '.$login['token']];

        $this->withHeaders($headers)
            ->postJson('/api/carts', ['service_id' => $service->id, 'qty' => 1])
            ->assertCreated();

        $cart = $this->withHeaders($headers)
            ->postJson('/api/carts', ['service_id' => $service->id, 'qty' => 1])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.qty', 2)
            ->json('data.items.0');

        $this->withHeaders($headers)
            ->putJson('/api/carts/'.$cart['id'], ['qty' => 5])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.qty', 5);
    }
}
