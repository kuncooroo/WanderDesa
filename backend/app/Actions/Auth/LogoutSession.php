<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutSession
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit->write(
                action: 'auth.logout',
                actorType: 'user',
                actorId: $user->id,
                entityType: 'user',
                entityId: $user->id,
                meta: ['channel' => 'web'],
                request: $request,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
