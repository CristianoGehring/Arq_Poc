<?php

namespace App\Actions\Customer;

use App\DTOs\Customer\CreateCustomerDTO;
use App\Events\CustomerCreated;
use App\Exceptions\CustomerAlreadyExistsException;
use App\Exceptions\InvalidCustomerDataException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateCustomerAction
{
    /**
     * Cria um novo cliente
     *
     * @throws CustomerAlreadyExistsException
     * @throws InvalidCustomerDataException
     */
    public function execute(CreateCustomerDTO $dto): Customer
    {
        // Validação de regra de negócio
        if (Customer::where('email', $dto->email)->exists()) {
            throw new CustomerAlreadyExistsException($dto->email);
        }

        if (Customer::where('document', $dto->document)->exists()) {
            throw new InvalidCustomerDataException(
                'document',
                'Document already exists'
            );
        }

        return DB::transaction(function () use ($dto) {
            $customer = Customer::create($dto->toArray());

            event(new CustomerCreated($customer));

            return $customer;
        });
    }
}
