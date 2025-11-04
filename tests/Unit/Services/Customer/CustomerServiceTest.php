<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Customer;

use App\DTOs\Customer\CreateCustomerDTO;
use App\DTOs\Customer\UpdateCustomerDTO;
use App\Enums\CustomerStatus;
use App\Events\CustomerCreated;
use App\Events\CustomerDeleted;
use App\Events\CustomerUpdated;
use App\Exceptions\CustomerAlreadyExistsException;
use App\Exceptions\CustomerException;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(CustomerRepositoryInterface::class);
        $this->service = new CustomerService($this->repository);
    }

    public function test_create_customer_successfully(): void
    {
        Event::fake();

        $dto = new CreateCustomerDTO(
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678900',
            phone: '11999999999',
        );

        $customer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678900',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $customer->id = 1;

        $this->repository
            ->shouldReceive('existsByEmail')
            ->once()
            ->with('joao@example.com')
            ->andReturn(false);

        $this->repository
            ->shouldReceive('existsByDocument')
            ->once()
            ->with('12345678900')
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($customer);

        $result = $this->service->create($dto);

        $this->assertInstanceOf(Customer::class, $result);
        Event::assertDispatched(CustomerCreated::class);
    }

    public function test_create_throws_exception_when_email_exists(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678900',
        );

        $this->repository
            ->shouldReceive('existsByEmail')
            ->once()
            ->with('joao@example.com')
            ->andReturn(true);

        $this->expectException(CustomerAlreadyExistsException::class);

        $this->service->create($dto);
    }

    public function test_create_throws_exception_when_document_exists(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678900',
        );

        $this->repository
            ->shouldReceive('existsByEmail')
            ->once()
            ->andReturn(false);

        $this->repository
            ->shouldReceive('existsByDocument')
            ->once()
            ->with('12345678900')
            ->andReturn(true);

        $this->expectException(CustomerAlreadyExistsException::class);

        $this->service->create($dto);
    }

    public function test_update_customer_successfully(): void
    {
        Event::fake();

        $dto = new UpdateCustomerDTO(
            name: 'João Silva Santos',
            phone: '11988888888',
        );

        $existingCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678900',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $existingCustomer->id = 1;

        $updatedCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva Santos',
            'email' => 'joao@example.com',
            'document' => '12345678900',
            'phone' => '11988888888',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $updatedCustomer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($existingCustomer);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::any())
            ->andReturn($updatedCustomer);

        $result = $this->service->update(1, $dto);

        $this->assertInstanceOf(Customer::class, $result);
        Event::assertDispatched(CustomerUpdated::class);
    }

    public function test_update_throws_exception_when_customer_not_found(): void
    {
        $dto = new UpdateCustomerDTO(name: 'João Silva Santos');

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(CustomerNotFoundException::class);

        $this->service->update(999, $dto);
    }

    public function test_update_throws_exception_when_customer_is_blocked(): void
    {
        $dto = new UpdateCustomerDTO(name: 'João Silva Santos');

        $blockedCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'status' => CustomerStatus::BLOCKED,
        ]);
        $blockedCustomer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($blockedCustomer);

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Cliente bloqueado não pode ser modificado');

        $this->service->update(1, $dto);
    }

    public function test_delete_customer_successfully(): void
    {
        Event::fake();

        $customer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $customer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($customer);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->delete(1);

        $this->assertTrue($result);
        Event::assertDispatched(CustomerDeleted::class);
    }

    public function test_delete_throws_exception_when_customer_not_found(): void
    {
        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(CustomerNotFoundException::class);

        $this->service->delete(999);
    }

    public function test_activate_customer_successfully(): void
    {
        Event::fake();

        $customer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::INACTIVE,
        ]);
        $customer->id = 1;

        $activatedCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $activatedCustomer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($customer);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['status' => CustomerStatus::ACTIVE])
            ->andReturn($activatedCustomer);

        $result = $this->service->activate(1);

        $this->assertInstanceOf(Customer::class, $result);
        Event::assertDispatched(CustomerUpdated::class);
    }

    public function test_deactivate_customer_successfully(): void
    {
        Event::fake();

        $customer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $customer->id = 1;

        $deactivatedCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::INACTIVE,
        ]);
        $deactivatedCustomer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($customer);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['status' => CustomerStatus::INACTIVE])
            ->andReturn($deactivatedCustomer);

        $result = $this->service->deactivate(1);

        $this->assertInstanceOf(Customer::class, $result);
        Event::assertDispatched(CustomerUpdated::class);
    }

    public function test_block_customer_successfully(): void
    {
        Event::fake();

        $customer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::ACTIVE,
        ]);
        $customer->id = 1;

        $blockedCustomer = new Customer([
            'id' => 1,
            'name' => 'João Silva',
            'status' => CustomerStatus::BLOCKED,
        ]);
        $blockedCustomer->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($customer);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['status' => CustomerStatus::BLOCKED])
            ->andReturn($blockedCustomer);

        $result = $this->service->block(1);

        $this->assertInstanceOf(Customer::class, $result);
        Event::assertDispatched(CustomerUpdated::class);
    }
}
