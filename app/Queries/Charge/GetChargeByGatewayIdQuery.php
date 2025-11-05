<?php

namespace App\Queries\Charge;

use App\Models\Charge;

class GetChargeByGatewayIdQuery
{
    /**
     * Busca cobrança por ID do gateway
     */
    public function execute(string $gatewayChargeId): ?Charge
    {
        return Charge::where('gateway_charge_id', $gatewayChargeId)
            ->with(['customer', 'paymentGateway'])
            ->first();
    }
}
