<?php

namespace App\Enums;

enum PaymentStatusName: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}