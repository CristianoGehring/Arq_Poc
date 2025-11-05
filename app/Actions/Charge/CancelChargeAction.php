<?php

namespace App\Actions\Charge;

use App\Enums\ChargeStatus;
use App\Events\ChargeCancelled;
use App\Exceptions\ChargeCannotBeCancelledException;
use App\Exceptions\ChargeNotFoundException;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;

class CancelChargeAction
{
    /**
     * Cancela cobrança
     *
     * @throws ChargeNotFoundException
     * @throws ChargeCannotBeCancelledException
     */
    public function execute(int $id, string $reason): Charge
    {
        $charge = Charge::find($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        if ($charge->status === ChargeStatus::PAID) {
            throw new ChargeCannotBeCancelledException('Charge already paid');
        }

        if ($charge->status === ChargeStatus::CANCELLED) {
            throw new ChargeCannotBeCancelledException('Charge already cancelled');
        }

        if ($charge->status === ChargeStatus::REFUNDED) {
            throw new ChargeCannotBeCancelledException('Charge already refunded');
        }

        return DB::transaction(function () use ($charge, $reason) {
            $charge->update([
                'status' => ChargeStatus::CANCELLED,
                'metadata' => array_merge($charge->metadata ?? [], [
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now()->toIso8601String(),
                ])
            ]);

            event(new ChargeCancelled($charge));

            return $charge->fresh();
        });
    }
}
