<?php

namespace App\Enums;

enum IdempotencyActorType: string
{
    case Device = 'device';
    case User = 'user';
}
