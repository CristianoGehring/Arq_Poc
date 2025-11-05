<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ChargeAlreadyPaidException extends ChargeException
{
    protected int $statusCode = 422;

    public function __construct(int $id)
    {
        parent::__construct("Charge #{$id} is already paid and cannot be modified");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'charge_already_paid',
        ], $this->statusCode);
    }
}
