<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function find(int $id): ?Customer;

    public function findByEmail(string $email): ?Customer;

    public function findByDocument(string $document): ?Customer;

    public function create(array $data): Customer;

    public function update(int $id, array $data): Customer;

    public function delete(int $id): bool;

    public function existsByEmail(string $email): bool;

    public function existsByDocument(string $document): bool;

    public function paginate(int $perPage = 15): LengthAwarePaginator;
}
