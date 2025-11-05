<?php

namespace App\Queries\Charge;

use App\Models\Charge;

class GetChargeByIdQuery
{
    /**
     * Busca cobrança por ID
     */
    public function execute(int $id): ?Charge
    {
        return Charge::with(['customer', 'paymentGateway'])
            ->find($id);
    }
}
