<?php

namespace App\Enums;

enum UserTypeName: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Secretary = 'secretary';
}