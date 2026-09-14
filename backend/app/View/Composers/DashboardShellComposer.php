<?php

namespace App\View\Composers;

use App\Support\Navigation\NavBuilder;
use Illuminate\View\View;

final class DashboardShellComposer
{
    public function __construct(
        private readonly NavBuilder $nav,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if ($user === null) {
            $view->with([
                'navGroups' => [],
                'breadcrumbs' => [],
            ]);

            return;
        }

        $view->with([
            'navGroups' => $this->nav->grouped($user),
            'breadcrumbs' => $this->nav->breadcrumbs(),
        ]);
    }
}
