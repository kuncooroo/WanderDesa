<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\AuditWriter;
use Illuminate\Http\Request;

final class LoginWithApiToken
{
    public function __construct(
        private readonly AuthenticateStaffCredentials $authenticate,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return array{user: User, plain_text_token: string}
     */
    public function handle(string $email, string $password, string $deviceName, Request $request): array
    {
        $user = $this->authenticate->handle($email, $password, $request, 'api');

        $token = $user->createToken($deviceName);

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->write(
            action: 'auth.login_success',
            actorType: 'user',
            actorId: $user->id,
            entityType: 'user',
            entityId: $user->id,
            meta: [
                'channel' => 'api',
                'token_name' => $deviceName,
            ],
            request: $request,
        );

        return [
            'user' => $user->load('roles'),
            'plain_text_token' => $token->plainTextToken,
        ];
    }
}
