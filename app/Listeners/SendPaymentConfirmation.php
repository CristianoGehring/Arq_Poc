<?php

namespace App\Listeners;

use App\Events\ChargePaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmation implements ShouldQueue
{
    public function handle(ChargePaid $event): void
    {
        // Enviar confirmação de pagamento
        $charge = $event->charge;
        $customer = $charge->customer;

        // TODO: Implementar envio de confirmação
        Log::info("Payment confirmation sent", [
            'charge_id' => $charge->id,
            'customer_id' => $customer->id,
        ]);
    }
}
