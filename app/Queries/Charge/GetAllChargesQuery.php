<?php

namespace App\Queries\Charge;

use App\Models\Charge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllChargesQuery
{
    /**
     * Lista todas as cobranças paginadas
     */
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Charge::with(['customer', 'paymentGateway'])
            ->latest()
            ->paginate($perPage);
    }
}
