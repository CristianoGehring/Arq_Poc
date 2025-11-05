<?php

namespace App\Events;

use App\Models\Customer;

class CustomerDeleted
{
    public function __construct(
        public readonly Customer $customer
    ) {}
}
