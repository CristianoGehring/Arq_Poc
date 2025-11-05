<?php

namespace Tests\Unit\Actions\Charge;

use App\Actions\Charge\CreateChargeAction;
use App\DTOs\Charge\CreateChargeDTO;
use App\Enums\PaymentMethod;
use App\Exceptions\CustomerNotFoundException;
use App\Exceptions\InvalidChargeDataException;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChargeActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateChargeAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateChargeAction();
    }

    /** @test */
    public function it_creates_charge_successfully(): void
    {
        $customer = Customer::factory()->create();

        $dto = new CreateChargeDTO(
            customerId: $customer->id,
            amount: 150.50,
            description: 'Test Charge',
            paymentMethod: PaymentMethod::PIX,
            dueDate: now()->addDays(7)->toDateString()
        );

        $charge = $this->action->execute($dto);

        $this->assertInstanceOf(Charge::class, $charge);
        $this->assertEquals(150.50, $charge->amount);
        $this->assertDatabaseHas('charges', ['description' => 'Test Charge']);
    }

    /** @test */
    public function it_throws_exception_for_nonexistent_customer(): void
    {
        $dto = new CreateChargeDTO(
            customerId: 999,
            amount: 150.50,
            description: 'Test Charge',
            paymentMethod: PaymentMethod::PIX,
            dueDate: now()->addDays(7)->toDateString()
        );

        $this->expectException(CustomerNotFoundException::class);

        $this->action->execute($dto);
    }

    /** @test */
    public function it_throws_exception_for_negative_amount(): void
    {
        $customer = Customer::factory()->create();

        $dto = new CreateChargeDTO(
            customerId: $customer->id,
            amount: -10,
            description: 'Test Charge',
            paymentMethod: PaymentMethod::PIX,
            dueDate: now()->addDays(7)->toDateString()
        );

        $this->expectException(InvalidChargeDataException::class);

        $this->action->execute($dto);
    }

    /** @test */
    public function it_throws_exception_for_past_due_date(): void
    {
        $customer = Customer::factory()->create();

        $dto = new CreateChargeDTO(
            customerId: $customer->id,
            amount: 150.50,
            description: 'Test Charge',
            paymentMethod: PaymentMethod::PIX,
            dueDate: now()->subDays(1)->toDateString()
        );

        $this->expectException(InvalidChargeDataException::class);

        $this->action->execute($dto);
    }
}
