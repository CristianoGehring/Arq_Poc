<?php

namespace App\Jobs;

use App\Actions\Charge\UpdateChargeStatusAction;
use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncChargeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public int $chargeId
    ) {}

    public function handle(UpdateChargeStatusAction $action): void
    {
        $charge = Charge::find($this->chargeId);

        if (!$charge || !$charge->gateway_charge_id) {
            return;
        }

        // TODO: Buscar status do gateway
        // $gatewayStatus = PaymentGatewayService::getChargeStatus($charge->gateway_charge_id);
        // $action->execute($charge->id, ChargeStatus::from($gatewayStatus), ['synced_at' => now()]);

        Log::info("Charge status synced", ['charge_id' => $this->chargeId]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed to sync charge status", [
            'charge_id' => $this->chargeId,
            'error' => $exception->getMessage(),
        ]);
    }
}
