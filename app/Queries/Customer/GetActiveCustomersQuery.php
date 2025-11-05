<?php

namespace App\Queries\Customer;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetActiveCustomersQuery
{
    /**
     * Lista apenas clientes ativos
     */
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::active()
            ->with(['charges' => fn($q) => $q->latest()->limit(5)])
            ->latest()
            ->paginate($perPage);
    }
}
