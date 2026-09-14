<?php

namespace App\Enums;

enum TicketValidateResult: string
{
    case Allow = 'ALLOW';
    case Deny = 'DENY';
}
