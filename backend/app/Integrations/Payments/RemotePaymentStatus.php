<?php

namespace App\Integrations\Payments;

enum RemotePaymentStatus: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Pending = 'pending';
    case Unknown = 'unknown';
}
