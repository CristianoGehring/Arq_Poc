<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_charge(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/charges', [
            'customer_id' => $customer->id,
            'amount' => 150.50,
            'description' => 'Test Charge',
            'payment_method' => 'pix',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'amount', 'status']]);
        $this->assertDatabaseHas('charges', ['description' => 'Test Charge']);
    }

    /** @test */
    public function it_validates_customer_exists(): void
    {
        $response = $this->postJson('/api/v1/charges', [
            'customer_id' => 999,
            'amount' => 150.50,
            'description' => 'Test Charge',
            'payment_method' => 'pix',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['errors' => ['customer_id' => ['Customer not found']]]);
    }

    /** @test */
    public function it_validates_amount_positive(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/charges', [
            'customer_id' => $customer->id,
            'amount' => -10,
            'description' => 'Test Charge',
            'payment_method' => 'pix',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_cannot_cancel_paid_charge(): void
    {
        $charge = Charge::factory()->create(['status' => ChargeStatus::PAID]);

        $response = $this->postJson("/api/v1/charges/{$charge->id}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'charge_cannot_be_cancelled']);
    }

    /** @test */
    public function it_can_cancel_pending_charge(): void
    {
        $charge = Charge::factory()->create(['status' => ChargeStatus::PENDING]);

        $response = $this->postJson("/api/v1/charges/{$charge->id}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => ChargeStatus::CANCELLED->value,
        ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_charge(): void
    {
        $response = $this->getJson('/api/v1/charges/999');

        $response->assertNotFound();
        $response->assertJson(['error' => 'charge_not_found']);
    }

    /** @test */
    public function it_can_filter_charges_by_status(): void
    {
        $customer = Customer::factory()->create();
        Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING]);
        Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PAID]);

        $response = $this->getJson('/api/v1/charges?status[]=pending');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_can_filter_charges_by_date_range(): void
    {
        $customer = Customer::factory()->create();
        $date1 = now()->addDays(5);
        $date2 = now()->addDays(15);

        Charge::factory()->create(['customer_id' => $customer->id, 'due_date' => $date1]);
        Charge::factory()->create(['customer_id' => $customer->id, 'due_date' => $date2]);

        $response = $this->getJson("/api/v1/charges?date_from={$date1->toDateString()}&date_to={$date1->toDateString()}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
