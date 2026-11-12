<?php

namespace App\Enums;

enum AdminActionName: string
{
    case GrantOwner = 'grant_owner';
    case RevokeOwner = 'revoke_owner';
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case GrantAdmin = 'grant_admin';
    case RevokeAdmin = 'revoke_admin';

    public function label(): string
    {
        return match ($this) {
            self::GrantOwner => 'Otorgó propiedad',
            self::RevokeOwner => 'Revocó propiedad',
            self::Activate => 'Activó cuenta',
            self::Deactivate => 'Desactivó cuenta',
            self::GrantAdmin => 'Otorgó rol de administrador',
            self::RevokeAdmin => 'Revocó rol de administrador',
        };
    }
}
