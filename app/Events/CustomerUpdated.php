<?php

namespace App\Events;

use App\Models\Customer;

class CustomerUpdated
{
    public function __construct(
        public readonly Customer $customer
    ) {}
}
