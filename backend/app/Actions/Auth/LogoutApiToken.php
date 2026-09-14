<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutApiToken
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $user, Request $request): bool
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->audit->write(
            action: 'auth.logout',
            actorType: 'user',
            actorId: $user->id,
            entityType: 'user',
            entityId: $user->id,
            meta: ['channel' => 'api'],
            request: $request,
        );

        return true;
    }
}
