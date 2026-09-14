<?php

namespace App\Actions\Payments;

use App\Enums\IdempotencyScope;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\Payment;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyOutcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

final class ConfirmCashPayment
{
    public function __construct(
        private readonly IdempotencyManager $idempotency,
        private readonly MarkPaymentPaid $markPaid,
    ) {}

    public function handle(
        User $principal,
        Payment $payment,
        string $idempotencyKey,
        ?Request $request = null,
    ): Payment {
        Authorizer::authorize($principal, PermissionName::PaymentsCreate);

        $canonical = [
            'payment_id' => (int) $payment->id,
            'received' => true,
        ];

        $result = $this->idempotency->run(
            $idempotencyKey,
            IdempotencyScope::PaymentConfirmCash,
            IdempotencyActor::fromPrincipal($principal),
            $canonical,
            function () use ($principal, $payment, $request): IdempotencyOutcome {
                $paid = $this->confirm($principal, $payment, $request);

                return new IdempotencyOutcome('payment', (int) $paid->id, 200, $paid);
            },
        );

        if ($result->replayed) {
            return Payment::query()
                ->with(['order', 'tickets'])
                ->findOrFail($result->resourceId);
        }

        /** @var Payment $paid */
        $paid = $result->value;

        return $paid->relationLoaded('order')
            ? $paid
            : Payment::query()->with(['order', 'tickets'])->findOrFail((int) $paid->id);
    }

    public function assertStaffPrincipal(Device|User|null $principal): User
    {
        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff may confirm cash payments.');
        }

        return $principal;
    }

    private function confirm(User $principal, Payment $payment, ?Request $request): Payment
    {
        $fresh = Payment::query()->with('order')->findOrFail($payment->id);

        if (! $fresh->isCash()) {
            throw new DomainException(
                'order.state_conflict',
                'Only cash payments can be confirmed with confirm-cash.',
                409,
            );
        }

        if (! $fresh->isOpen() && ! $fresh->isPaid()) {
            throw new DomainException(
                'order.state_conflict',
                'This payment cannot be confirmed.',
                409,
            );
        }

        return $this->markPaid->handle(
            $fresh,
            $principal,
            $request,
            'cash',
        );
    }
}
