<?php

namespace App\Queries\Charge;

use App\Models\Charge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPendingChargesQuery
{
    /**
     * Lista apenas cobranças pendentes
     */
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Charge::pending()
            ->with(['customer', 'paymentGateway'])
            ->latest()
            ->paginate($perPage);
    }
}
