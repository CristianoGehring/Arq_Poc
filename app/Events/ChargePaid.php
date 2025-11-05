<?php

namespace App\Events;

use App\Models\Charge;

class ChargePaid
{
    public function __construct(
        public readonly Charge $charge
    ) {}
}
