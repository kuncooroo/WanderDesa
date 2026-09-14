<?php

namespace App\Support\Tickets;

use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DeviceAbilities;

final class TicketAccess
{
    public static function deviceOwns(Device $device, Ticket $ticket): bool
    {
        if ((int) $ticket->issued_by_device_id === (int) $device->getKey()) {
            return true;
        }

        $order = $ticket->relationLoaded('order') ? $ticket->order : $ticket->order()->first();

        return $order !== null && (int) $order->device_id === (int) $device->getKey();
    }

    public static function deviceCanRead(Device $device, Ticket $ticket): bool
    {
        if (! $device->tokenCan(DeviceAbilities::TICKETS_READ_OWN)
            && ! $device->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)) {
            return false;
        }

        return self::deviceOwns($device, $ticket);
    }

    public static function deviceCanPrint(Device $device, Ticket $ticket): bool
    {
        if (! $device->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)
            && ! $device->tokenCan(DeviceAbilities::TICKETS_READ_OWN)) {
            return false;
        }

        return self::deviceOwns($device, $ticket);
    }

    public static function includeQrPayload(Device|User|null $principal, Ticket $ticket): bool
    {
        if ($principal instanceof Device) {
            return self::deviceCanPrint($principal, $ticket);
        }

        if ($principal instanceof User) {
            return $principal->can('print', $ticket) || $principal->can('reprint', $ticket);
        }

        return false;
    }
}
