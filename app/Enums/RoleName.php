<?php

namespace App\Enums;

enum RoleName: string
{
    case Usuario = 'usuario';
    case Medico = 'medico';
    case Administrador = 'administrador';
}
