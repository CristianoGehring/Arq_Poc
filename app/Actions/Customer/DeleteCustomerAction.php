<?php

namespace App\Actions\Customer;

use App\Events\CustomerDeleted;
use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomerAction
{
    /**
     * Remove cliente (soft delete)
     *
     * @throws CustomerNotFoundException
     */
    public function execute(int $id): bool
    {
        $customer = Customer::find($id);

        if (!$customer) {
            throw new CustomerNotFoundException($id);
        }

        return DB::transaction(function () use ($customer) {
            $deleted = $customer->delete();

            if ($deleted) {
                event(new CustomerDeleted($customer));
            }

            return $deleted;
        });
    }
}
