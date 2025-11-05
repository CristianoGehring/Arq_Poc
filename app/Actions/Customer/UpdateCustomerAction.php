<?php

namespace App\Actions\Customer;

use App\DTOs\Customer\UpdateCustomerDTO;
use App\Events\CustomerUpdated;
use App\Exceptions\CustomerAlreadyExistsException;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class UpdateCustomerAction
{
    /**
     * Atualiza cliente existente
     *
     * @throws CustomerNotFoundException
     * @throws CustomerAlreadyExistsException
     */
    public function execute(int $id, UpdateCustomerDTO $dto): Customer
    {
        $customer = Customer::find($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        // Validar email único (se estiver sendo alterado)
        if ($dto->email && $dto->email !== $customer->email) {
            if (Customer::where('email', $dto->email)->where('id', '!=', $id)->exists()) {
                throw new CustomerAlreadyExistsException($dto->email);
            }
        }

        return DB::transaction(function () use ($customer, $dto) {
            $customer->update($dto->toArray());
            $customer->refresh();

            event(new CustomerUpdated($customer));

            return $customer;
        });
    }
}
