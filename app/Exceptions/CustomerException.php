<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CustomerException extends Exception
{
    public static function invalidStatus(string $status): self
    {
        return new self("Status inválido: {$status}");
    }

    public static function cannotModifyBlocked(): self
    {
        return new self('Cliente bloqueado não pode ser modificado');
    }

    public static function operationFailed(string $operation): self
    {
        return new self("Operação '{$operation}' falhou");
    }
}
