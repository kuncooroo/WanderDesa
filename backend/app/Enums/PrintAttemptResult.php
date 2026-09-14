<?php

namespace App\Enums;

enum PrintAttemptResult: string
{
    case Success = 'success';
    case Failed = 'failed';
}
