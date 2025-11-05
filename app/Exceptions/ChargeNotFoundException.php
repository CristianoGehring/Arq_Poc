<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ChargeNotFoundException extends ChargeException
{
    protected int $statusCode = 404;

    public function __construct(int $id)
    {
        parent::__construct("Charge #{$id} not found");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'charge_not_found',
        ], $this->statusCode);
    }
}
