<?php

namespace App\Integrations\Payments;

enum ProviderCancelOutcome: string
{
    case Cancelled = 'cancelled';
    case AlreadySettled = 'already_settled';
}
