<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private readonly Customer $model
    ) {
    }

    public function find(int $id): ?Customer
    {
        /** @var Customer|null */
        return $this->model->newQuery()->find($id);
    }

    public function findByEmail(string $email): ?Customer
    {
        /** @var Customer|null */
        return $this->model
            ->newQuery()
            ->where('email', $email)
            ->first();
    }

    public function findByDocument(string $document): ?Customer
    {
        /** @var Customer|null */
        return $this->model
            ->newQuery()
            ->where('document', $document)
            ->first();
    }

    public function create(array $data): Customer
    {
        /** @var Customer */
        return $this->model->newQuery()->create($data);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->find($id);

        if ($customer === null) {
            throw CustomerNotFoundException::withId($id);
        }

        $customer->update($data);

        return $customer->fresh();
    }

    public function delete(int $id): bool
    {
        $customer = $this->find($id);

        if ($customer === null) {
            throw CustomerNotFoundException::withId($id);
        }

        return (bool) $customer->delete();
    }

    public function existsByEmail(string $email): bool
    {
        return $this->model
            ->newQuery()
            ->where('email', $email)
            ->exists();
    }

    public function existsByDocument(string $document): bool
    {
        return $this->model
            ->newQuery()
            ->where('document', $document)
            ->exists();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
