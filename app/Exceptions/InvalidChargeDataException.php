<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidChargeDataException extends ChargeException
{
    protected int $statusCode = 422;

    public function __construct(string $field, string $reason)
    {
        parent::__construct("Invalid charge data: {$field} - {$reason}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_charge_data',
        ], $this->statusCode);
    }
}
