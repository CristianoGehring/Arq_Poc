<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_customer(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'document' => '12345678900',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'name', 'email']]);
        $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
    }

    /** @test */
    public function it_returns_422_for_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'document' => '12345678900',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'customer_already_exists']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_customer(): void
    {
        $response = $this->getJson('/api/v1/customers/999');

        $response->assertNotFound();
        $response->assertJson(['error' => 'customer_not_found']);
    }
}
