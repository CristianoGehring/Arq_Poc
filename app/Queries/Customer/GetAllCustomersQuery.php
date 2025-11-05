<?php

namespace App\Queries\Customer;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllCustomersQuery
{
    /**
     * Lista todos os clientes paginados
     */
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::with(['charges' => fn($q) => $q->latest()->limit(5)])
            ->latest()
            ->paginate($perPage);
    }
}
