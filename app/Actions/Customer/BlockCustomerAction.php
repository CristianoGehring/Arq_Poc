<?php

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;

class BlockCustomerAction
{
    /**
     * Bloqueia cliente
     *
     * @throws CustomerNotFoundException
     */
    public function execute(int $id): Customer
    {
        $customer = Customer::find($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        $customer->status = CustomerStatus::BLOCKED;
        $customer->save();

        return $customer;
    }
}
