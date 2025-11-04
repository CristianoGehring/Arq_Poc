<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerQueryService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $repository
    ) {
    }

    public function findById(int $id): ?Customer
    {
        return $this->repository->find($id);
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->repository->findByEmail($email);
    }

    public function findByDocument(string $document): ?Customer
    {
        return $this->repository->findByDocument($document);
    }

    public function getActive(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('document', 'like', "%{$term}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
