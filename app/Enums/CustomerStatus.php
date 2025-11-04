<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::INACTIVE => 'Inativo',
            self::BLOCKED => 'Bloqueado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canBeModified(): bool
    {
        return $this !== self::BLOCKED;
    }
}
