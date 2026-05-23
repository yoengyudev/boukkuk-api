<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
