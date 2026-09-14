<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Disabled = 'disabled';
}
