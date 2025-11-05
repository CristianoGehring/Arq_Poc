<?php

namespace App\Listeners;

use App\Events\ChargeCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendChargeNotification implements ShouldQueue
{
    public function handle(ChargeCreated $event): void
    {
        // Enviar notificação (email/SMS) quando cobrança é criada
        $charge = $event->charge;
        $customer = $charge->customer;

        // TODO: Implementar envio de notificação
        Log::info("Charge created notification sent", [
            'charge_id' => $charge->id,
            'customer_id' => $customer->id,
        ]);
    }
}
