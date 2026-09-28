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
}
