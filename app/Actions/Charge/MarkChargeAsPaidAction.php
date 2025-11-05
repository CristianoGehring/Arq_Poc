<?php

namespace App\Actions\Charge;

use App\Enums\ChargeStatus;
use App\Events\ChargePaid;
use App\Exceptions\ChargeCannotBeCancelledException;
use App\Exceptions\ChargeNotFoundException;
use App\Models\Charge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarkChargeAsPaidAction
{
    /**
     * Marca cobrança como paga
     *
     * @throws ChargeNotFoundException
     * @throws ChargeCannotBeCancelledException
     */
    public function execute(int $id, ?string $paidAt = null): Charge
    {
        $charge = Charge::find($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        if ($charge->status === ChargeStatus::PAID) {
            return $charge; // Já está paga, retorna sem erro
        }

        if ($charge->status === ChargeStatus::CANCELLED) {
            throw new ChargeCannotBeCancelledException('Cannot mark cancelled charge as paid');
        }

        return DB::transaction(function () use ($charge, $paidAt) {
            $charge->update([
                'status' => ChargeStatus::PAID,
                'paid_at' => $paidAt ? Carbon::parse($paidAt) : now(),
            ]);

            event(new ChargePaid($charge));

            return $charge->fresh();
        });
    }
}
