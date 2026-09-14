<?php

namespace App\Enums;

enum TicketDenyCode: string
{
    case NotFound = 'DENY_NOT_FOUND';
    case InvalidAuth = 'DENY_INVALID_AUTH';
    case WrongDestination = 'DENY_WRONG_DESTINATION';
    case NotPaid = 'DENY_NOT_PAID';
    case NotActive = 'DENY_NOT_ACTIVE';
    case Expired = 'DENY_EXPIRED';
    case Cancelled = 'DENY_CANCELLED';
    case Refunded = 'DENY_REFUNDED';
    case AlreadyUsed = 'DENY_ALREADY_USED';
    case Unauthorized = 'DENY_UNAUTHORIZED';
}
