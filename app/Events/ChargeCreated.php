<?php

namespace App\Events;

use App\Models\Charge;

class ChargeCreated
{
    public function __construct(
        public readonly Charge $charge
    ) {}
}
