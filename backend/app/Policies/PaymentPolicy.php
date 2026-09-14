<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Payment;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsCreate);
    }

    public function confirmCash(User $user, Payment $payment): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsCreate);
    }

    public function refund(User $user, ?Payment $payment = null): bool
    {
        return Authorizer::check($user, PermissionName::PaymentsRefund);
    }
}
