<?php

namespace App\Actions\Charge;

use App\Enums\ChargeStatus;
use App\Events\ChargeRefunded;
use App\Exceptions\ChargeCannotBeCancelledException;
use App\Exceptions\ChargeNotFoundException;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;

class RefundChargeAction
{
    /**
     * Reembolsa cobrança paga
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

        if ($charge->status !== ChargeStatus::PAID) {
            throw new ChargeCannotBeCancelledException('Only paid charges can be refunded');
        }

        return DB::transaction(function () use ($charge, $reason) {
            $charge->update([
                'status' => ChargeStatus::REFUNDED,
                'metadata' => array_merge($charge->metadata ?? [], [
                    'refund_reason' => $reason,
                    'refunded_at' => now()->toIso8601String(),
                ])
            ]);

            event(new ChargeRefunded($charge));

            return $charge->fresh();
        });
    }
}
