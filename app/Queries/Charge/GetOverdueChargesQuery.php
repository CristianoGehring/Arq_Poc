<?php

namespace App\Queries\Charge;

use App\Models\Charge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetOverdueChargesQuery
{
    /**
     * Lista apenas cobranças vencidas
     */
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Charge::overdue()
            ->with(['customer', 'paymentGateway'])
            ->oldest('due_date')
            ->paginate($perPage);
    }
}
