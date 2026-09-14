<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Issued = 'issued';
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
