<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidCustomerDataException extends CustomerException
{
    protected int $statusCode = 422;

    public function __construct(string $field, string $reason)
    {
        parent::__construct("Invalid customer data: {$field} - {$reason}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_customer_data',
        ], $this->statusCode);
    }
}
