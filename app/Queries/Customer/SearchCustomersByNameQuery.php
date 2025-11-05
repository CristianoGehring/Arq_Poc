<?php

namespace App\Queries\Customer;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchCustomersByNameQuery
{
    /**
     * Busca clientes por nome
     */
    public function execute(string $name, int $perPage = 15): LengthAwarePaginator
    {
        return Customer::where('name', 'like', "%{$name}%")
            ->latest()
            ->paginate($perPage);
    }
}
