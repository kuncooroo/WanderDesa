<?php

namespace App\Livewire\Layout;

use App\Support\Navigation\NavBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Optional Livewire sidebar fragment (TASK-018). Primary shell uses Blade partials;
 * this component remains available for progressive enhancement.
 */
class SidebarNav extends Component
{
    public function render(NavBuilder $nav)
    {
        $user = Auth::user();

        return view('livewire.layout.sidebar-nav', [
            'navGroups' => $user ? $nav->grouped($user) : [],
        ]);
    }
}
