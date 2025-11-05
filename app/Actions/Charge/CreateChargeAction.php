<?php

namespace App\Actions\Charge;

use App\DTOs\Charge\CreateChargeDTO;
use App\Events\ChargeCreated;
use App\Exceptions\CustomerNotFoundException;
use App\Exceptions\InvalidChargeDataException;
use App\Models\Charge;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateChargeAction
{
    /**
     * Cria uma nova cobrança
     *
     * @throws CustomerNotFoundException
     * @throws InvalidChargeDataException
     */
    public function execute(CreateChargeDTO $dto): Charge
    {
        // Validar customer existe
        $customer = Customer::find($dto->customerId);
        if (!$customer) {
            throw new CustomerNotFoundException($dto->customerId);
        }

        // Validar amount positivo
        if ($dto->amount <= 0) {
            throw new InvalidChargeDataException('amount', 'Amount must be greater than 0');
        }

        // Validar due_date não está no passado
        $dueDate = Carbon::parse($dto->dueDate);
        if ($dueDate->isPast()) {
            throw new InvalidChargeDataException('due_date', 'Due date cannot be in the past');
        }

        return DB::transaction(function () use ($dto) {
            $charge = Charge::create($dto->toArray());

            event(new ChargeCreated($charge));

            return $charge;
        });
    }
}
