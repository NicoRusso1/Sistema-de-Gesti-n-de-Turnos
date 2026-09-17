<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'SuperAdmin';
    case Administrator = 'Administrator';
    case OperationalUser = 'OperationalUser';
}