<?php

namespace App\Integrations\Gate;

use App\Models\CheckIn;
use App\Models\Gate;

/**
 * MVP default: gate hardware off — staff opens the physical barrier on ALLOW UI.
 */
final class NullGateController implements GateController
{
    public function open(?Gate $gate, CheckIn $checkIn): void
    {
        // no-op
    }
}
