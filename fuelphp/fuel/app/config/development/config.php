<?php

use Fuel\Core\Fuel;

return [
    // Profiler is disabled in dev: its inline scripts violate CSP and pollute the console.
    // Re-enable temporarily if you need request-level timing/queries; prefer `make test-unit`
    // and the app log under `fuelphp/fuel/app/logs/` for day-to-day diagnostics.
    'profiling' => false,
    'log_threshold' => Fuel::L_DEBUG,
];
