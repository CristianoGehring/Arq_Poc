<?php

namespace App\Events;

use App\Models\Charge;

class ChargeRefunded
{
    public function __construct(
        public readonly Charge $charge
    ) {}
}
