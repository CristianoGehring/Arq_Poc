<?php

namespace App\Exceptions;

use Exception;

class CustomerException extends Exception
{
    public function __construct(string $message = '', int $code = 400)
    {
        parent::__construct($message, $code);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'customer_exception',
        ], $this->getCode());
    }
}
