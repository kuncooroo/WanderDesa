<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Order;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * Staff order authorization (docs/07). Device principals are authorized in
 * CreateOrder / CancelOrder / OrderController (token ability + ownership).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::OrdersView);
    }

    public function view(User $user, Order $order): bool
    {
        return Authorizer::check($user, PermissionName::OrdersView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::OrdersCreate);
    }

    public function update(User $user, Order $order): bool
    {
        return Authorizer::check($user, PermissionName::OrdersUpdate);
    }

    public function cancel(User $user, Order $order): bool
    {
        return Authorizer::check($user, PermissionName::OrdersCancel);
    }
}
