<?php

namespace App\Actions\Auth;

use App\Exceptions\AuthException;
use App\Models\User;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthenticateStaffCredentials
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Validate staff email/password. Inactive users are rejected explicitly.
     *
     * @throws AuthException
     */
    public function handle(string $email, string $password, Request $request, string $channel): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->audit->write(
                action: 'auth.login_failed',
                actorType: $user === null ? 'anonymous' : 'user',
                actorId: $user?->id,
                entityType: 'user',
                entityId: $user?->id,
                meta: [
                    'email' => $email,
                    'reason' => 'invalid_credentials',
                    'channel' => $channel,
                ],
                request: $request,
            );

            throw new AuthException(
                'auth.invalid_credentials',
                'The provided credentials are incorrect.',
                401,
            );
        }

        if (! $user->is_active) {
            $this->audit->write(
                action: 'auth.login_failed',
                actorType: 'user',
                actorId: $user->id,
                entityType: 'user',
                entityId: $user->id,
                meta: [
                    'email' => $email,
                    'reason' => 'user_inactive',
                    'channel' => $channel,
                ],
                request: $request,
            );

            throw new AuthException(
                'auth.user_inactive',
                'This account is inactive.',
                403,
            );
        }

        return $user;
    }
}
