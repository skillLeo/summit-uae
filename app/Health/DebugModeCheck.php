<?php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/** Debug mode: off in production (a debug page would show the configuration); on elsewhere is only noted. */
final class DebugModeCheck extends Check
{
    protected ?string $name = 'debug';

    public function run(): Result
    {
        $environment = (string) app()->environment();
        $debug = (bool) config('app.debug');
        if (! $debug) {
            return Outcome::ok('debug_off', ['environment' => $environment]);
        }

        return app()->isProduction()
            ? Outcome::failed('debug_production', ['environment' => $environment])
            : Outcome::ok('debug_local', ['environment' => $environment]);
    }
}
