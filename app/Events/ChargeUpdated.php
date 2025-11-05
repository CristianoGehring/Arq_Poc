<?php

namespace App\Events;

use App\Models\Charge;

class ChargeUpdated
{
    public function __construct(
        public readonly Charge $charge
    ) {}
}
