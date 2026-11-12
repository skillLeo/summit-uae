<?php

namespace App\Support;

use App\Content\Cms\Editing\Diff;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * The activity log (docs/briefs/design-2.1-and-admin-cms.md §12.2; spatie/laravel-activitylog 5.1.3, log "admin"):
 *
 * - CMS content records: created, updated (the changed fields only), deleted, restored (LogsActivity in
 *   App\Models\Concerns\HasCmsFields). The content import writes with the query builder, so it logs nothing.
 * - Requests: only the workflow fields and the reference (App\Models\Enquiry), never a visitor's personal data.
 * - Users: name, e-mail, admin flag, deactivation, admin language (here, from the model events). Never the password,
 *   the remember token or the two-factor secrets.
 * - Sign-ins: auth.login (and users.last_login_at), auth.logout, auth.failed. A failed sign-in keeps neither the
 *   address typed nor the password: only the time.
 * - Roles given or taken away (spatie/laravel-permission's events, when config permission.events_enabled is on).
 *
 * Every entry passes through scrub(): any key that looks like a secret (password, secret, token, recovery …) is
 * masked before it is written, wherever it sits in the changes or the properties.
 */
final class ActivityLogging
{
    public const LOG = Audit::LOG;

    public const MASK = '********';

    /** Keys whose values are never written to or shown from the log. */
    public const SECRET = '/(password|passwd|secret|token|recovery|two_factor|app_authentication|api_key|private_key|ip_hash|idempotency)/i';

    /** The user fields the log keeps (everything else on users is either a secret or noise). */
    public const USER_FIELDS = ['name', 'email', 'is_admin', 'deactivated_at', 'admin_locale'];

    /** The scrub callback is static in the package: added once per process (tests boot many applications). */
    private static bool $scrubbing = false;

    public static function register(): void
    {
        // a trashed record still names itself in the log
        config(['activitylog.include_soft_deleted_subjects' => true]);

        if (! self::$scrubbing) {
            self::$scrubbing = true;
            LogActivityAction::beforeLogging(function (Activity $activity): void {
                self::scrub($activity);
            });
        }

        Event::listen(Login::class, [self::class, 'login']);
        Event::listen(Logout::class, [self::class, 'logout']);
        Event::listen(Failed::class, [self::class, 'failed']);
        Event::listen(RoleAttachedEvent::class, fn (RoleAttachedEvent $e) => self::roles($e->model, $e->rolesOrIds, 'roles.attached'));
        Event::listen(RoleDetachedEvent::class, fn (RoleDetachedEvent $e) => self::roles($e->model, $e->rolesOrIds, 'roles.detached'));

        User::created(fn (User $user) => self::user($user, 'created'));
        User::updated(fn (User $user) => self::user($user, 'updated'));
        User::deleted(fn (User $user) => self::user($user, 'deleted'));
    }

    // ------------------------------------------------------------------ sign-ins

    public static function login(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }
        $user = $event->user;
        try {
            // quietly: the sign-in time is not a change of the user to log
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        } catch (Throwable) {
            // the column may be missing before the Phase 7 migration
        }
        self::write('auth.login', $user, $user);
    }

    public static function logout(Logout $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }
        self::write('auth.logout', $event->user, $event->user);
    }

    /** Neither the address typed nor the password: only that a sign-in failed, and when. */
    public static function failed(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }
        self::write('auth.failed', null, null, ['guard' => 'web']);
    }

    // ------------------------------------------------------------------ users and roles

    public static function user(User $user, string $event): void
    {
        $fields = self::USER_FIELDS;
        $changes = match ($event) {
            'created' => ['attributes' => self::pick($user->getAttributes(), $fields, $user)],
            'deleted' => ['old' => self::pick($user->getAttributes(), $fields, $user)],
            default => self::dirty($user, $fields),
        };
        if ($changes === null || ($changes['attributes'] ?? []) === [] && ($changes['old'] ?? []) === []) {
            return; // only a secret, the remember token, the sign-in time … changed
        }
        self::write($event, $user, null, [], $changes);
    }

    /** @param  mixed  $rolesOrIds  ids, a Role, or a list of them (spatie/laravel-permission) */
    public static function roles(Model $model, mixed $rolesOrIds, string $event): void
    {
        if (! $model instanceof User) {
            return;
        }
        try {
            $items = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds]);
            $ids = $items->map(fn ($r) => $r instanceof Role ? $r->getKey() : $r)->filter(fn ($r) => is_scalar($r))->all();
            $names = Role::query()->whereKey($ids)->pluck('name')->sort()->values()->all();
        } catch (Throwable) {
            $names = [];
        }
        self::write($event, $model, null, ['roles' => $names]);
    }

    // ------------------------------------------------------------------ content records

    /**
     * Called from HasCmsFields::beforeActivityLogged(): keeps a content entry small and readable.
     * - updated with a draft change: the draft column holds the whole working copy, so only the draft fields that
     *   differ are kept (against the live columns when there was no draft before, or is none after);
     * - deleted (to the Trash) and restored: only deleted_at (the record itself is unchanged); a permanent delete keeps
     *   the full record;
     * - a "label" property names the record when it is gone.
     */
    public static function content(Model $record, Activity $activity, string $event): void
    {
        try {
            $activity->properties = collect($activity->properties ?? [])->put('label', Registry::label($record, 'en'));
            $changes = $activity->attribute_changes?->toArray() ?? [];

            if (($event === 'deleted' && ! (method_exists($record, 'isForceDeleting') && $record->isForceDeleting())) || $event === 'restored') {
                $at = $record->getAttribute('deleted_at');
                $at = $at instanceof \DateTimeInterface ? $at->format(DATE_ATOM) : $at;
                $activity->attribute_changes = collect($event === 'deleted'
                    ? ['attributes' => ['deleted_at' => $at], 'old' => ['deleted_at' => null]]
                    : ['attributes' => ['deleted_at' => null], 'old' => ['deleted_at' => $changes['old']['deleted_at'] ?? null]]);

                return;
            }

            if ($event !== 'updated' || ! Registry::supports($record)) {
                return;
            }
            $hasNew = array_key_exists('draft', $changes['attributes'] ?? []);
            $hasOld = array_key_exists('draft', $changes['old'] ?? []);
            if (! $hasNew && ! $hasOld) {
                return;
            }
            $live = Publisher::live($record);
            $old = Diff::flatten(self::decode($changes['old']['draft'] ?? null) ?? $live);
            $new = Diff::flatten(self::decode($changes['attributes']['draft'] ?? null) ?? $live);
            $before = [];
            $after = [];
            foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $path) {
                if (($old[$path] ?? null) !== ($new[$path] ?? null)) {
                    $before[$path] = $old[$path] ?? null;
                    $after[$path] = $new[$path] ?? null;
                }
            }
            unset($changes['attributes']['draft'], $changes['old']['draft']);
            if ($after !== []) {
                $changes['attributes']['draft'] = $after;
                $changes['old']['draft'] = $before;
            }
            $activity->attribute_changes = collect($changes);
        } catch (Throwable) {
            // the log must never block a save
        }
    }

    // ------------------------------------------------------------------ helpers

    /** Mask every value whose key looks like a secret, in the changes and the properties. */
    public static function scrub(Activity $activity): void
    {
        if ($activity->attribute_changes !== null) {
            $activity->attribute_changes = collect(self::mask($activity->attribute_changes->toArray()));
        }
        if ($activity->properties !== null) {
            $activity->properties = collect(self::mask($activity->properties->toArray()));
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET, $key) === 1) {
                $data[$key] = $value === null ? null : self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }

        return $data;
    }

    /** A JSON text (raw translatable columns, drafts) as its structure; anything else as it is. */
    public static function decode(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ($value[0] === '{' || $value[0] === '[')) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $changes
     */
    private static function write(string $event, ?Model $subject, ?User $causer, array $properties = [], array $changes = []): void
    {
        try {
            $logger = activity(self::LOG)->event($event)->withProperties($properties);
            if ($changes !== []) {
                $logger->withChanges($changes);
            }
            if ($subject !== null) {
                $logger->performedOn($subject);
            }
            $causer ??= auth()->user() instanceof User ? auth()->user() : null;
            $causer !== null ? $logger->causedBy($causer) : $logger->causedByAnonymous();
            $logger->log($event);
        } catch (Throwable) {
            // the log must never block a sign-in or a save
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private static function pick(array $raw, array $fields, Model $model): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $raw)) {
                $out[$field] = self::value($model, $field, $raw[$field]);
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $fields
     * @return array{attributes: array<string, mixed>, old: array<string, mixed>}|null
     */
    private static function dirty(Model $model, array $fields): ?array
    {
        $changed = array_intersect_key($model->getChanges(), array_flip($fields));
        if ($changed === []) {
            return null;
        }
        $attributes = [];
        $old = [];
        foreach (array_keys($changed) as $field) {
            $attributes[$field] = self::value($model, $field, $model->getAttributes()[$field] ?? null);
            $old[$field] = self::value($model, $field, $model->getRawOriginal($field));
        }

        return ['attributes' => $attributes, 'old' => $old];
    }

    private static function value(Model $model, string $field, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        if ($model->hasCast($field, ['bool', 'boolean'])) {
            return (bool) $raw;
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format(DATE_ATOM);
        }

        return $raw;
    }
}
