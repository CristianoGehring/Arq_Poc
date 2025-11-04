<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class CustomerAlreadyExistsException extends Exception
{
    protected $code = Response::HTTP_CONFLICT;

    public static function withEmail(string $email): self
    {
        return new self("Já existe um cliente cadastrado com o email '{$email}'");
    }

    public static function withDocument(string $document): self
    {
        return new self("Já existe um cliente cadastrado com o documento '{$document}'");
    }
}
