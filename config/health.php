<?php

use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\EloquentHealthResultStore;

/*
| Health (docs/briefs/design-2.1-and-admin-cms.md §12.4; spatie/laravel-health 1.40.4; System, Health screen). The
| checks are registered in App\Providers\SystemServiceProvider (App\Health\*): database, cache, queue, scheduler,
| mail, disk, last backup, debug mode. The latest results are kept in the cache (no table): the screen's
| "Run checks" button and the scheduled `health:check` (every five minutes, routes/console.php) refresh them.
| Notifications are off: the mailer is "log" for now; staff look at the screen.
*/

return [
    /*
     * The latest results live in the application cache (CACHE_STORE), no history table.
     */
    'result_stores' => [
        Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => env('HEALTH_CACHE_STORE', env('CACHE_STORE', 'file')),
        ],

        /*
        EloquentHealthResultStore::class => [
            'connection' => env('HEALTH_DB_CONNECTION', env('DB_CONNECTION')),
            'model' => HealthCheckResultHistoryItem::class,
            'keep_history_for_days' => 5,
        ],

        Spatie\Health\ResultStores\JsonFileHealthResultStore::class => [
            'disk' => 's3',
            'path' => 'health.json',
        ],

        Spatie\Health\ResultStores\InMemoryHealthResultStore::class,
        */
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     */
    'notifications' => [
        /*
         * Notifications will only get sent if this option is set to `true`.
         */
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', false),

        'notifications' => [
            CheckFailedNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => Notifiable::class,

        /*
         * When checks start failing, you could potentially end up getting
         * a notification every minute.
         *
         * With this setting, notifications are throttled. By default, you'll
         * only get one notification per hour.
         */
        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        /*
         * When set to true, notifications will only be sent when at least one
         * check has a 'failed' status. Warnings will be ignored.
         */
        'only_on_failure' => false,

        'mail' => [
            'to' => env('HEALTH_TO_ADDRESS', ''),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK_URL', ''),

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],
    ],

    /*
     * You can let Oh Dear monitor the results of all health checks. This way, you'll
     * get notified of any problems even if your application goes totally down. Via
     * Oh Dear, you can also have access to more advanced notification options.
     */
    'oh_dear_endpoint' => [
        'enabled' => false,

        /*
         * When this option is enabled, the checks will run before sending a response.
         * Otherwise, we'll send the results from the last time the checks have run.
         */
        'always_send_fresh_results' => true,

        /*
         * The secret that is displayed at the Application Health settings at Oh Dear.
         */
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),

        /*
         * The URL that should be configured in the Application health settings at Oh Dear.
         */
        'url' => '/oh-dear-health-check-results',
    ],

    /*
     * You can specify a heartbeat URL for the Horizon check.
     * This URL will be pinged if the Horizon check is successful.
     * This way you can get notified if Horizon goes down.
     */
    'horizon' => [
        'heartbeat_url' => env('HORIZON_HEARTBEAT_URL'),
    ],

    /*
     * You can specify a heartbeat URL for the Schedule check.
     * This URL will be pinged if the Schedule check is successful.
     * This way you can get notified if the schedule fails to run.
     */
    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    /*
     * You can set a theme for the local results page
     *
     * - light: light mode
     * - dark: dark mode
     */
    'theme' => 'light',

    /*
     * When enabled, completed `HealthQueueJob`s will be displayed
     * in Horizon's silenced jobs screen.
     */
    'silence_health_queue_job' => true,

    /*
     * Thresholds of the Summit checks (App\Health\*).
     */
    'summit' => [
        // disk: warning from this share of the disk used, failed from the second (percent)
        'disk_warning_percent' => (int) env('HEALTH_DISK_WARNING_PERCENT', 80),
        'disk_failed_percent' => (int) env('HEALTH_DISK_FAILED_PERCENT', 90),
        // last backup: warning when the newest backup is older than this many hours, failed from the second
        'backup_warning_hours' => (int) env('HEALTH_BACKUP_WARNING_HOURS', 48),
        'backup_failed_hours' => (int) env('HEALTH_BACKUP_FAILED_HOURS', 96),
        // queue: the heartbeat job (every minute) must have run within this many minutes
        'queue_max_minutes' => (int) env('HEALTH_QUEUE_MAX_MINUTES', 5),
        // scheduler: the heartbeat command (every minute) must have run within this many minutes
        'schedule_max_minutes' => (int) env('HEALTH_SCHEDULE_MAX_MINUTES', 2),
    ],

    /*
     * The response code to use for HealthCheckJsonResultsController when a health
     * check has failed
     */
    'json_results_failure_status' => 200,

    /*
     * You can specify a secret token that needs to be sent in the X-Secret-Token for secured access.
     */
    'secret_token' => env('HEALTH_SECRET_TOKEN'),

/**
 * By default, conditionally skipped health checks are treated as failures.
 * You can override this behavior by uncommenting the configuration below.
 *
 * @link https://spatie.be/docs/laravel-health/v1/basic-usage/conditionally-running-or-modifying-checks
 */
    // 'treat_skipped_as_failure' => false
];
