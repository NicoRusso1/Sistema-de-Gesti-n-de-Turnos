<?php

namespace App\Enums;

enum DocumentIssueStatus: string
{
    case Pending = 'pendiente';
    case Issued = 'emitida';
    case Failed = 'fallida';
}
