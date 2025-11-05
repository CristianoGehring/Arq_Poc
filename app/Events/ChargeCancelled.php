<?php

namespace App\Events;

use App\Models\Charge;

class ChargeCancelled
{
    public function __construct(
        public readonly Charge $charge
    ) {}
}
