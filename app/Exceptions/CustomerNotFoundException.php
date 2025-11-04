<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class CustomerNotFoundException extends Exception
{
    protected $code = Response::HTTP_NOT_FOUND;

    public static function withId(int $id): self
    {
        return new self("Cliente com ID {$id} não encontrado");
    }

    public static function withEmail(string $email): self
    {
        return new self("Cliente com email '{$email}' não encontrado");
    }

    public static function withDocument(string $document): self
    {
        return new self("Cliente com documento '{$document}' não encontrado");
    }
}
