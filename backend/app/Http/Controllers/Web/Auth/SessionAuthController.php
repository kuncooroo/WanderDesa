<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Auth\LoginWithSession;
use App\Actions\Auth\LogoutSession;
use App\Exceptions\AuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, LoginWithSession $login): RedirectResponse
    {
        try {
            $login->handle(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                remember: $request->boolean('remember'),
                request: $request,
            );
        } catch (AuthException $e) {
            $message = $e->errorCode === 'auth.user_inactive'
                ? 'Akun ini tidak aktif.'
                : 'Email atau password salah.';

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $message]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, LogoutSession $logout): RedirectResponse
    {
        $logout->handle($request);

        return redirect()->route('login');
    }
}
