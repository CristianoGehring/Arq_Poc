<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/customers';

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(5)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'document',
                        'phone',
                        'address',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_can_create_customer(): void
    {
        $customerData = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678900',
            'phone' => '11999999999',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-567',
            ],
        ];

        $response = $this->postJson($this->baseUrl, $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'document',
                    'phone',
                    'address',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment([
                'name' => 'João Silva',
                'email' => 'joao@example.com',
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'joao@example.com',
            'document' => '12345678900',
        ]);
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $response = $this->getJson("{$this->baseUrl}/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $customer->id,
                'name' => 'Maria Santos',
                'email' => 'maria@example.com',
            ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Pedro Oliveira',
            'email' => 'pedro@example.com',
        ]);

        $updateData = [
            'name' => 'Pedro Oliveira Santos',
            'phone' => '11988888888',
        ];

        $response = $this->putJson("{$this->baseUrl}/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Pedro Oliveira Santos',
                'phone' => '11988888888',
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Pedro Oliveira Santos',
            'phone' => '11988888888',
        ]);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$customer->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'document']);
    }

    public function test_prevents_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test User',
            'email' => 'duplicate@example.com',
            'document' => '98765432100',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_prevents_duplicate_document(): void
    {
        Customer::factory()->create(['document' => '12345678900']);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document' => '12345678900',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    public function test_returns_404_for_nonexistent_customer(): void
    {
        $response = $this->getJson("{$this->baseUrl}/999999");

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Cliente não encontrado',
            ]);
    }

    public function test_can_search_customers(): void
    {
        Customer::factory()->create([
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ]);

        Customer::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $response = $this->getJson("{$this->baseUrl}?search=João");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'João Silva'])
            ->assertJsonMissing(['name' => 'Maria Santos']);
    }

    public function test_validates_email_format(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'document' => '12345678900',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validates_name_minimum_length(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => 'Ab',
            'email' => 'test@example.com',
            'document' => '12345678900',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_address_fields_are_required_when_address_is_provided(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document' => '12345678900',
            'address' => [
                'street' => 'Rua Teste',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address.number', 'address.city', 'address.state', 'address.zip_code']);
    }

    public function test_can_paginate_customers(): void
    {
        Customer::factory()->count(20)->create();

        $response = $this->getJson("{$this->baseUrl}?per_page=10");

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }
}
