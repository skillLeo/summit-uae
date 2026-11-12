<?php

use App\Providers\AppServiceProvider;
use App\Providers\DesignServiceProvider;

return [
    AppServiceProvider::class,
    DesignServiceProvider::class,
    App\Providers\SystemServiceProvider::class, // activity log, backups, health (System screens, Phase 7)
    App\Providers\Filament\AdminPanelProvider::class, // admin: internal Filament panel (/admin), Phase F
];
