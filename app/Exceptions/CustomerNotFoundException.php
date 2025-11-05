<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class CustomerNotFoundException extends CustomerException
{
    protected int $statusCode = 404;

    public function __construct(int $id)
    {
        parent::__construct("Customer #{$id} not found");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'customer_not_found',
        ], $this->statusCode);
    }
}
