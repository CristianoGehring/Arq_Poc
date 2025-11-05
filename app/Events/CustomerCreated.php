<?php

namespace App\Events;

use App\Models\Customer;

class CustomerCreated
{
    public function __construct(
        public readonly Customer $customer
    ) {}
}
