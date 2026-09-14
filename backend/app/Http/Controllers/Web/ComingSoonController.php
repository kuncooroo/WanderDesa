<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Authorization\NavPermissionMap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function __invoke(Request $request, string $module): View
    {
        $user = $request->user();
        abort_unless($user !== null && NavPermissionMap::canSee($user, $module), 403);

        // No MVP stub modules remain; keep controller for future gated placeholders.
        $titles = [];

        abort_unless(array_key_exists($module, $titles), 404);

        return view('dashboard.coming-soon', [
            'title' => $titles[$module],
            'module' => $module,
        ]);
    }
}
