<?php

namespace Tests\Unit\Queries\Charge;

use App\Models\Charge;
use App\Models\Customer;
use App\Queries\Charge\GetChargeByIdQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetChargeByIdQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_charge_by_id(): void
    {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create(['customer_id' => $customer->id]);

        $query = new GetChargeByIdQuery();
        $result = $query->execute($charge->id);

        $this->assertInstanceOf(Charge::class, $result);
        $this->assertEquals($charge->id, $result->id);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_charge(): void
    {
        $query = new GetChargeByIdQuery();
        $result = $query->execute(999);

        $this->assertNull($result);
    }
}
