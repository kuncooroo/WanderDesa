<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginWithSession
{
    public function __construct(
        private readonly AuthenticateStaffCredentials $authenticate,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(string $email, string $password, bool $remember, Request $request): User
    {
        $user = $this->authenticate->handle($email, $password, $request, 'web');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->write(
            action: 'auth.login_success',
            actorType: 'user',
            actorId: $user->id,
            entityType: 'user',
            entityId: $user->id,
            meta: [
                'channel' => 'web',
                'remember' => $remember,
            ],
            request: $request,
        );

        return $user;
    }
}
