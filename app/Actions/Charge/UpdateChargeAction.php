<?php

namespace App\Actions\Charge;

use App\DTOs\Charge\UpdateChargeDTO;
use App\Events\ChargeUpdated;
use App\Exceptions\ChargeAlreadyPaidException;
use App\Exceptions\ChargeNotFoundException;
use App\Exceptions\InvalidChargeDataException;
use App\Models\Charge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateChargeAction
{
    /**
     * Atualiza cobrança existente
     *
     * @throws ChargeNotFoundException
     * @throws ChargeAlreadyPaidException
     * @throws InvalidChargeDataException
     */
    public function execute(int $id, UpdateChargeDTO $dto): Charge
    {
        $charge = Charge::find($id);

        if (!$charge) {
            throw new ChargeNotFoundException($id);
        }

        // Validar se cobrança pode ser atualizada
        if (!$charge->canBeUpdated()) {
            throw new ChargeAlreadyPaidException($id);
        }

        // Validar amount positivo (se estiver sendo alterado)
        if ($dto->amount !== null && $dto->amount <= 0) {
            throw new InvalidChargeDataException('amount', 'Amount must be greater than 0');
        }

        // Validar due_date não está no passado (se estiver sendo alterado)
        if ($dto->dueDate !== null) {
            $dueDate = Carbon::parse($dto->dueDate);
            if ($dueDate->isPast()) {
                throw new InvalidChargeDataException('due_date', 'Due date cannot be in the past');
            }
        }

        return DB::transaction(function () use ($charge, $dto) {
            $charge->update($dto->toArray());
            $charge->refresh();

            event(new ChargeUpdated($charge));

            return $charge;
        });
    }
}
