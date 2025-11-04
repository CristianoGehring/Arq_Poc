<?php

declare(strict_types=1);

namespace App\Services\Customer;

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
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $repository
    ) {
    }

    public function create(CreateCustomerDTO $dto): Customer
    {
        $this->validateUniqueEmail($dto->email);
        $this->validateUniqueDocument($dto->document);

        return DB::transaction(function () use ($dto) {
            $customer = $this->repository->create([
                ...$dto->toArray(),
                'status' => CustomerStatus::ACTIVE,
            ]);

            event(new CustomerCreated($customer));

            return $customer;
        });
    }

    public function update(int $id, UpdateCustomerDTO $dto): Customer
    {
        if (!$dto->hasChanges()) {
            return $this->findOrFail($id);
        }

        $customer = $this->findOrFail($id);

        $this->validateCanModify($customer);

        if ($dto->email !== null && $dto->email !== $customer->email) {
            $this->validateUniqueEmail($dto->email);
        }

        return DB::transaction(function () use ($id, $dto) {
            $customer = $this->repository->update($id, $dto->toArray());

            event(new CustomerUpdated($customer));

            return $customer;
        });
    }

    public function delete(int $id): bool
    {
        $customer = $this->findOrFail($id);

        $this->validateCanModify($customer);

        return DB::transaction(function () use ($id, $customer) {
            $deleted = $this->repository->delete($id);

            if ($deleted) {
                event(new CustomerDeleted($customer));
            }

            return $deleted;
        });
    }

    public function activate(int $id): Customer
    {
        return $this->changeStatus($id, CustomerStatus::ACTIVE);
    }

    public function deactivate(int $id): Customer
    {
        return $this->changeStatus($id, CustomerStatus::INACTIVE);
    }

    public function block(int $id): Customer
    {
        return $this->changeStatus($id, CustomerStatus::BLOCKED);
    }

    private function changeStatus(int $id, CustomerStatus $status): Customer
    {
        $customer = $this->findOrFail($id);

        return DB::transaction(function () use ($id, $status) {
            $customer = $this->repository->update($id, ['status' => $status]);

            event(new CustomerUpdated($customer));

            return $customer;
        });
    }

    private function findOrFail(int $id): Customer
    {
        $customer = $this->repository->find($id);

        if ($customer === null) {
            throw CustomerNotFoundException::withId($id);
        }

        return $customer;
    }

    private function validateUniqueEmail(string $email): void
    {
        if ($this->repository->existsByEmail($email)) {
            throw CustomerAlreadyExistsException::withEmail($email);
        }
    }

    private function validateUniqueDocument(string $document): void
    {
        if ($this->repository->existsByDocument($document)) {
            throw CustomerAlreadyExistsException::withDocument($document);
        }
    }

    private function validateCanModify(Customer $customer): void
    {
        if (!$customer->canBeModified()) {
            throw CustomerException::cannotModifyBlocked();
        }
    }
}
