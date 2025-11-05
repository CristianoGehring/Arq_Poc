<?php

namespace Tests\Unit\Actions\Customer;

use App\Actions\Customer\CreateCustomerAction;
use App\DTOs\Customer\CreateCustomerDTO;
use App\Exceptions\CustomerAlreadyExistsException;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateCustomerAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateCustomerAction();
    }

    /** @test */
    public function it_creates_customer_successfully(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'John Doe',
            email: 'john@example.com',
            document: '12345678900'
        );

        $customer = $this->action->execute($dto);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
    }

    /** @test */
    public function it_throws_exception_for_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'john@example.com']);

        $dto = new CreateCustomerDTO(
            name: 'John Doe',
            email: 'john@example.com',
            document: '12345678900'
        );

        $this->expectException(CustomerAlreadyExistsException::class);

        $this->action->execute($dto);
    }
}
