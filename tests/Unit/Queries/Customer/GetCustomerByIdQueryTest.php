<?php

namespace Tests\Unit\Queries\Customer;

use App\Models\Customer;
use App\Queries\Customer\GetCustomerByIdQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCustomerByIdQueryTest extends TestCase
{
    use RefreshDatabase;

    private GetCustomerByIdQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetCustomerByIdQuery();
    }

    /** @test */
    public function it_retrieves_customer_by_id(): void
    {
        $customer = Customer::factory()->create();

        $foundCustomer = $this->query->execute($customer->id);

        $this->assertNotNull($foundCustomer);
        $this->assertEquals($customer->id, $foundCustomer->id);
    }

    /** @test */
    public function it_returns_null_if_customer_not_found(): void
    {
        $foundCustomer = $this->query->execute(999);

        $this->assertNull($foundCustomer);
    }
}
