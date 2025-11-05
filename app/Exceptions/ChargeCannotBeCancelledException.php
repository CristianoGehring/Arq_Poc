<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ChargeCannotBeCancelledException extends ChargeException
{
    protected int $statusCode = 422;

    public function __construct(string $reason)
    {
        parent::__construct("Charge cannot be cancelled: {$reason}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'charge_cannot_be_cancelled',
        ], $this->statusCode);
    }
}
