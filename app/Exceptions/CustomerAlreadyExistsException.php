<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class CustomerAlreadyExistsException extends CustomerException
{
    protected int $statusCode = 422;

    public function __construct(string $email)
    {
        parent::__construct("Customer with email '{$email}' already exists");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'customer_already_exists',
            'details' => [
                'field' => 'email',
                'issue' => 'duplicate'
            ]
        ], $this->statusCode);
    }
}
