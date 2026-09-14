<?php

namespace App\Integrations\Gate;

use App\Models\CheckIn;
use App\Models\Gate;

/**
 * Optional physical gate actuation after authoritative check-in ALLOW.
 */
interface GateController
{
    /**
     * Open only after check-in has committed. Never decides ALLOW itself.
     */
    public function open(?Gate $gate, CheckIn $checkIn): void;
}
