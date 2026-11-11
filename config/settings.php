<?php

/*
| spatie/laravel-settings (docs/briefs/design-2.1-and-admin-cms.md §12.3). The settings classes live in app/Settings and
| are discovered; their properties are created by the settings migrations in database/settings. The public site reads
| the stored values through App\Content\ContentRepository (cached with the published content), so the package's own
| cache stays off.
*/

return [

    'settings' => [],

    'setting_class_path' => app_path('Settings'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
    ],

    'encoder' => null,
    'decoder' => null,

    'cache' => [
        'enabled' => false,
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => false,
    ],

    'global_casts' => [
        DateTimeInterface::class => Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast::class,
        DateTimeZone::class => Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast::class,
    ],

    'auto_discover_settings' => [
        app_path('Settings'),
    ],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
