<?php

namespace App\Queries\Customer;

use App\Models\Customer;

class GetCustomerByIdQuery
{
    /**
     * Busca cliente por ID
     */
    public function execute(int $id): ?Customer
    {
        return Customer::with(['charges' => fn($q) => $q->latest()->limit(5)])
            ->find($id);
    }
}
