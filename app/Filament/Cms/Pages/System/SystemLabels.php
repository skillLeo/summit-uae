<?php

namespace App\Filament\Cms\Pages\System;

use App\Content\Cms\Editing\Diff;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Support\CmsLabels;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\ActivityLogging;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * How the System screens name things (lang/{locale}/system.php): the people, actions, record types and records of the
 * activity log, its field-by-field changes, and Dubai times. Values whose key looks like a secret are masked again
 * here (ActivityLogging::mask()), whatever an older entry may hold.
 */
final class SystemLabels
{
    public const TIMEZONE = 'Asia/Dubai';

    /** Filter value for "no user" (the system, the website form) and "no record". */
    public const SYSTEM = '__none';

    public static function dubai(mixed $time, string $format = 'j M Y, H:i'): string
    {
        if ($time === null || $time === '') {
            return '';
        }
        $time = $time instanceof \DateTimeInterface ? Carbon::instance($time) : Carbon::parse((string) $time, 'UTC');

        return $time->copy()->setTimezone(self::TIMEZONE)->translatedFormat($format);
    }

    // ------------------------------------------------------------------ people, actions, types

    public static function causer(Activity $activity): string
    {
        $causer = $activity->causer;
        if ($causer instanceof User) {
            return (string) $causer->name;
        }
        if ($activity->causer_id !== null) {
            return (string) __('system.activity.former_user', ['id' => $activity->causer_id]);
        }

        return $activity->getProperty('source') === 'website'
            ? (string) __('system.activity.website')
            : (string) __('system.activity.system');
    }

    public static function event(string $event): string
    {
        $key = 'system.activity.event.'.str_replace('.', '_', $event);
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($event);
    }

    public static function eventColor(string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'updated' => 'info',
            'deleted', 'force_delete', 'auth.failed', 'anonymise' => 'danger',
            'restored', 'restore', 'roles.attached', 'roles.detached' => 'warning',
            default => 'gray',
        };
    }

    /** @return class-string<Model>|null */
    public static function typeClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) ? $class : null;
    }

    public static function typeKey(?string $type): string
    {
        $class = self::typeClass($type);
        if ($class === null) {
            return Str::snake(class_basename((string) $type));
        }
        if (in_array($class, Registry::models(), true)) {
            return Registry::type($class);
        }
        $morph = array_search($class, Relation::morphMap(), true);

        return is_string($morph) ? $morph : Str::snake(class_basename($class));
    }

    public static function type(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }
        $key = 'system.activity.type.'.self::typeKey($type);
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline(self::typeKey($type));
    }

    /** The record an entry is about, by name: never a visitor's name (requests are named by their reference). */
    public static function record(Activity $activity): ?string
    {
        $subject = $activity->subject;
        $class = self::typeClass($activity->subject_type);
        $reference = $activity->getProperty('reference');

        if ($class === Enquiry::class || ($class === null && is_string($reference))) {
            return is_string($reference) ? $reference : ($subject instanceof Enquiry ? $subject->reference : null);
        }
        if ($subject instanceof User) {
            return (string) $subject->name;
        }
        if ($subject instanceof Model && Registry::supports($subject)) {
            return Registry::label($subject);
        }
        $label = $activity->getProperty('label');
        if (is_string($label) && $label !== '') {
            return $label;
        }

        return $activity->subject_id !== null ? (string) $activity->subject_id : null;
    }

    /** One line about the entry: the fields that changed, or the entry's details. */
    public static function summary(Activity $activity): string
    {
        $changes = self::changes($activity);
        $fields = array_values(array_unique([...array_keys($changes['attributes']), ...array_keys($changes['old'])]));
        if ($fields !== []) {
            return (string) __('system.activity.fields', ['fields' => implode(', ', array_map(self::field(...), $fields))]);
        }
        $parts = [];
        foreach (self::properties($activity) as $row) {
            $parts[] = $row['label'].': '.$row['value'];
        }

        return implode('; ', $parts);
    }

    /** @return array<string, string> */
    public static function causerOptions(): array
    {
        $ids = Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id')->all();
        $users = User::query()->whereKey($ids)->orderBy('name')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();

        return [self::SYSTEM => (string) __('system.activity.system_or_website')] + array_combine(array_map('strval', array_keys($users)), $users);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        $out = [];
        foreach (Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type') as $type) {
            $out[(string) $type] = self::type((string) $type);
        }
        asort($out);

        return $out + [self::SYSTEM => (string) __('system.activity.no_record')];
    }

    /** @return array<string, string> */
    public static function eventOptions(): array
    {
        $out = [];
        foreach (Activity::query()->whereNotNull('event')->distinct()->pluck('event') as $event) {
            $out[(string) $event] = self::event((string) $event);
        }
        asort($out);

        return $out;
    }

    // ------------------------------------------------------------------ changes

    /** @return array{attributes: array<string, mixed>, old: array<string, mixed>} */
    public static function changes(Activity $activity): array
    {
        $changes = ActivityLogging::mask($activity->attribute_changes?->toArray() ?? []);

        return [
            'attributes' => is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [],
            'old' => is_array($changes['old'] ?? null) ? $changes['old'] : [],
        ];
    }

    /** @return list<array{field: string, path: string, before: string, after: string}> */
    public static function diff(Activity $activity): array
    {
        $changes = self::changes($activity);
        $rows = [];
        foreach (array_unique([...array_keys($changes['old']), ...array_keys($changes['attributes'])]) as $field) {
            $before = self::flat($changes['old'][$field] ?? null, (string) $field);
            $after = self::flat($changes['attributes'][$field] ?? null, (string) $field);
            $paths = array_unique([...array_keys($before), ...array_keys($after)]);
            if ($paths === []) {
                $rows[] = ['field' => self::field((string) $field), 'path' => '', 'before' => '', 'after' => ''];

                continue;
            }
            foreach ($paths as $path) {
                $old = $before[$path] ?? '';
                $new = $after[$path] ?? '';
                if ($old === $new && array_key_exists((string) $field, $changes['old']) && array_key_exists((string) $field, $changes['attributes'])) {
                    continue;
                }
                $sub = $path === $field ? '' : substr((string) $path, strlen((string) $field) + 1);
                $rows[] = ['field' => self::field((string) $field), 'path' => $sub, 'before' => $old, 'after' => $new];
            }
        }

        return $rows;
    }

    /** The changes as one text (the CSV column): "field: before …, after …" per row. */
    public static function diffText(Activity $activity): string
    {
        $lines = [];
        foreach (self::diff($activity) as $row) {
            $lines[] = (string) __('system.activity.diff_line', [
                'field' => $row['field'].($row['path'] !== '' ? ' ('.$row['path'].')' : ''),
                'before' => $row['before'],
                'after' => $row['after'],
            ]);
        }

        return implode("\n", $lines);
    }

    /** @return list<array{label: string, value: string}> the entry's details, without the record's own name */
    public static function properties(Activity $activity): array
    {
        $props = ActivityLogging::mask($activity->properties?->toArray() ?? []);
        unset($props['label'], $props['source']);
        if (self::typeClass($activity->subject_type) === Enquiry::class) {
            unset($props['reference']);
        }
        $out = [];
        foreach ($props as $key => $value) {
            $text = is_array($value) ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE), $value)) : self::value($value);
            $out[] = ['label' => self::property((string) $key), 'value' => $text];
        }

        return $out;
    }

    public static function field(string $field): string
    {
        $key = 'system.activity.field.'.$field;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : CmsLabels::key($field);
    }

    public static function property(string $key): string
    {
        $tkey = 'system.activity.property.'.$key;
        $label = __($tkey);

        return is_string($label) && $label !== $tkey ? $label : CmsLabels::key($key);
    }

    /** Fields that hold a user id: shown as the user's name. */
    public const USER_FIELDS = ['updated_by', 'assignee_id', 'user_id', 'created_by'];

    /** @var array<string, string> */
    private static array $users = [];

    private static function userName(string $id): string
    {
        return self::$users[$id] ??= (string) (User::query()->whereKey($id)->value('name') ?? __('system.activity.former_user', ['id' => $id]));
    }

    /** @return array<string, string> path => printable value */
    private static function flat(mixed $value, string $field): array
    {
        if (in_array($field, self::USER_FIELDS, true) && is_scalar($value) && preg_match('/^\d+$/', (string) $value) === 1) {
            return [$field => self::userName((string) $value)];
        }
        $value = ActivityLogging::decode($value);
        if (is_array($value)) {
            return array_map(self::value(...), Diff::flatten($value, $field));
        }
        if ($value === null || $value === '') {
            return [];
        }

        return [$field => self::value($value)];
    }

    public static function value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value) || $value === 'true' || $value === 'false') {
            return (string) __(($value === true || $value === 'true') ? 'system.yes' : 'system.no');
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value) === 1) {
            return self::dubai($value, 'j M Y, H:i');
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
