<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\HomeSnapshotBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HomeSnapshotBuilder $snapshot): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return view('dashboard.home', [
            'user' => $user,
            'snapshot' => $snapshot->build($user),
        ]);
    }
}
