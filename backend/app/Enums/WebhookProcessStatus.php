<?php

namespace App\Enums;

enum WebhookProcessStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
}
