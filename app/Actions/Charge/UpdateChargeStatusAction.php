<?php

namespace App\Actions\Charge;

use App\Enums\ChargeStatus;
use App\Events\ChargePaid;
use App\Exceptions\ChargeNotFoundException;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;

class UpdateChargeStatusAction
{
    /**
     * Atualiza status da cobrança (usado por sincronização com gateway)
     *
     * @throws ChargeNotFoundException
     */
    public function execute(int $id, ChargeStatus $status, ?array $metadata = null): Charge
    {
        $charge = Charge::find($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        return DB::transaction(function () use ($charge, $status, $metadata) {
            $updateData = ['status' => $status];

            // Se mudou para pago, adicionar paid_at
            if ($status === ChargeStatus::PAID && !$charge->paid_at) {
                $updateData['paid_at'] = now();
            }

            // Merge metadata se fornecido
            if ($metadata) {
                $updateData['metadata'] = array_merge($charge->metadata ?? [], $metadata);
            }

            $charge->update($updateData);

            // Disparar evento apropriado
            if ($status === ChargeStatus::PAID) {
                event(new ChargePaid($charge));
            }

            return $charge->fresh();
        });
    }
}
