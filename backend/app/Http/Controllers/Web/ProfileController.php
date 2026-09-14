<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing('roles');

        return view('dashboard.profile', [
            'user' => $user,
        ]);
    }
}
