<?php

namespace App\Enums;

enum PaymentMethodName: string
{
    case Transfer = 'transfer';
    case Credit = 'credit';
    case Debit = 'debit';
}