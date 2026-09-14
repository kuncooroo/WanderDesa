<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * tickets.create / tickets.update are never human-granted (system after PAID).
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::TicketsView);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return Authorizer::check($user, PermissionName::TicketsView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::TicketsCreate);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return Authorizer::check($user, PermissionName::TicketsUpdate);
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        return Authorizer::check($user, PermissionName::TicketsCancel);
    }

    public function validateTicket(User $user, ?Ticket $ticket = null): bool
    {
        return Authorizer::check($user, PermissionName::TicketsValidate);
    }

    public function print(User $user, ?Ticket $ticket = null): bool
    {
        return Authorizer::check($user, PermissionName::TicketsPrint);
    }

    public function reprint(User $user, ?Ticket $ticket = null): bool
    {
        return Authorizer::check($user, PermissionName::TicketsReprint);
    }
}
