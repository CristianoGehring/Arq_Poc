<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;

class ActivateCustomerAction
{
    /**
     * Ativa cliente
     *
     * @throws CustomerNotFoundException
     */
    public function execute(int $id): Customer
    {
        $customer = Customer::find($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        $customer->status = CustomerStatus::ACTIVE;
        $customer->save();

        return $customer;
    }
}
