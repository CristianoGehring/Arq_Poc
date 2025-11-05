<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;

class DeactivateCustomerAction
{
    /**
     * Desativa cliente
     *
     * @throws CustomerNotFoundException
     */
    public function execute(int $id): Customer
    {
        $customer = Customer::find($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        $customer->status = CustomerStatus::INACTIVE;
        $customer->save();

        return $customer;
    }
}
