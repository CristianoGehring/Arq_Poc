<?php

namespace App\Queries\Charge;

use App\Models\Charge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetCustomerChargesQuery
{
    /**
     * Lista cobranças de um cliente específico
     */
    public function execute(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return Charge::query()
            ->where('customer_id', $customerId)
            ->with(['paymentGateway'])
            ->latest()
            ->paginate($perPage);
    }
}
