<?php

namespace Tests\Unit\Models;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_paid_scope(): void
    {
        $customer = Customer::factory()->create();
        Charge::factory()->count(2)->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PAID]);
        Charge::factory()->count(3)->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING]);

        $this->assertEquals(2, Charge::paid()->count());
    }

    /** @test */
    public function it_has_pending_scope(): void
    {
        $customer = Customer::factory()->create();
        Charge::factory()->count(2)->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PAID]);
        Charge::factory()->count(3)->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING]);

        $this->assertEquals(3, Charge::pending()->count());
    }

    /** @test */
    public function it_has_overdue_scope(): void
    {
        $customer = Customer::factory()->create();
        Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING, 'due_date' => now()->subDay()]);
        Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING, 'due_date' => now()->addDay()]);

        $this->assertEquals(1, Charge::overdue()->count());
    }

    /** @test */
    public function is_paid_accessor_works(): void
    {
        $paidCharge = Charge::factory()->make(['status' => ChargeStatus::PAID]);
        $pendingCharge = Charge::factory()->make(['status' => ChargeStatus::PENDING]);

        $this->assertTrue($paidCharge->isPaid());
        $this->assertFalse($pendingCharge->isPaid());
    }

    /** @test */
    public function can_be_cancelled_accessor_works(): void
    {
        $pendingCharge = Charge::factory()->make(['status' => ChargeStatus::PENDING]);
        $paidCharge = Charge::factory()->make(['status' => ChargeStatus::PAID]);
        $cancelledCharge = Charge::factory()->make(['status' => ChargeStatus::CANCELLED]);

        $this->assertTrue($pendingCharge->canBeCancelled());
        $this->assertFalse($paidCharge->canBeCancelled());
        $this->assertFalse($cancelledCharge->canBeCancelled());
    }
}
