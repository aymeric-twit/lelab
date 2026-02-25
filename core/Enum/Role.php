<?php

namespace Platform\Enum;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::User => 'Utilisateur',
        };
    }
}
