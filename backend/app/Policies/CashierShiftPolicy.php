<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\CashierShift;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class CashierShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsView)
            || Authorizer::check($user, PermissionName::PaymentsCreate);
    }

    public function view(User $user, CashierShift $cashierShift): bool
    {
        if (Authorizer::check($user, PermissionName::PaymentsView)) {
            return true;
        }

        return Authorizer::check($user, PermissionName::PaymentsCreate)
            && (int) $cashierShift->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsCreate);
    }

    public function update(User $user, CashierShift $cashierShift): bool
    {
        if (! Authorizer::check($user, PermissionName::PaymentsCreate)
            && ! Authorizer::check($user, PermissionName::PaymentsView)) {
            return false;
        }

        if ((int) $cashierShift->user_id === (int) $user->id) {
            return Authorizer::check($user, PermissionName::PaymentsCreate);
        }

        return Authorizer::check($user, PermissionName::PaymentsView);
    }

    public function close(User $user, CashierShift $cashierShift): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsCreate)
            && (int) $cashierShift->user_id === (int) $user->id
            && $cashierShift->isOpen();
    }

    public function delete(User $user, CashierShift $cashierShift): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsCreate)
            && (int) $cashierShift->user_id === (int) $user->id
            && $cashierShift->isOpen();
    }
}
