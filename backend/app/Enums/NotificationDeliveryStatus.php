<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pendiente';
    case Sent = 'enviada';
    case Failed = 'fallida';
}
