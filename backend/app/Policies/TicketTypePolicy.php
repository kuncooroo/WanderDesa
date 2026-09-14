<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class TicketTypePolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::TicketTypesView);
    }

    public function view(User $user, TicketType $ticketType): bool
    {
        return Authorizer::check($user, PermissionName::TicketTypesView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::TicketTypesManage);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return Authorizer::check($user, PermissionName::TicketTypesManage);
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return Authorizer::check($user, PermissionName::TicketTypesManage);
    }
}
