<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Status;
use App\Content\Cms\Tree;
use App\Models\Content\NavigationItem;
use App\Models\Content\NavigationMenu;
use App\Models\Revision;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Drafts, publishing, scheduling and revisions of a CMS record (docs/briefs/design-2.1-and-admin-cms.md §8.5).
 *
 *   live          the published columns, as the public site reads them (Registry::fields())
 *   working copy  what the editor sees: the draft when there is one, else the live columns
 *   saveDraft()   a published record keeps its live columns; the changes go to `draft` (null when nothing differs).
 *                 A record that was never published (status draft) has no live version, so its columns are written.
 *   publish()     applies the working copy, sets status published, writes a revision and replaces the cache version
 *   discard()     drops the draft
 *   schedule()    publish_at; App\Jobs\PublishScheduledContent publishes it when the time comes
 *   restore()     a revision becomes the new draft (never published directly)
 *
 * Every JSON value is stored as JSON text like the importer writes it, so an unchanged record stays byte-identical.
 */
final class Publisher
{
    public const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** Plain columns with a type the form must give back. */
    private const BOOLEANS = ['enabled', 'standalone', 'photo_pending', 'featured', 'new_tab'];

    private const INTEGERS = ['position', 'version', 'featured_position'];

    /**
     * The live (published) values of the editable columns.
     *
     * @return array<string, mixed>
     */
    public static function live(Model $record): array
    {
        $raw = $record->getAttributes();
        $out = [];
        foreach (Registry::fields($record) as $column => $kind) {
            $out[$column] = match ($kind) {
                Registry::PLAIN => self::plain($column, $raw[$column] ?? null),
                Registry::ITEMS => self::menuItems($record),
                default => self::decode($raw[$column] ?? null),
            };
        }

        return $out;
    }

    /** @return array<string, mixed> the draft when there is one, else the live values */
    public static function workingCopy(Model $record): array
    {
        $draft = self::draft($record);

        return $draft !== null ? self::normalize($record, $draft + self::live($record)) : self::live($record);
    }

    /** @return array<string, mixed>|null */
    public static function draft(Model $record): ?array
    {
        $draft = self::decode($record->getAttributes()['draft'] ?? null);

        return is_array($draft) ? $draft : null;
    }

    public static function hasDraft(Model $record): bool
    {
        return self::draft($record) !== null;
    }

    /**
     * Save the editor's data as the draft (or, for a record never published, as its columns).
     *
     * @param  array<string, mixed>  $data  values of Registry::fields() (others are ignored)
     */
    public static function saveDraft(Model $record, array $data, ?int $userId = null): Model
    {
        $fields = Registry::fields($record);
        $working = self::workingCopy($record);
        $data = array_intersect_key($data, $fields);
        foreach ($data as $column => $value) {
            if (in_array($fields[$column], [Registry::TREE, Registry::TRANSLATABLE, Registry::JSON], true) && array_key_exists($column, $working)) {
                $data[$column] = self::prune($value, $working[$column]);
            }
        }
        $data = self::normalize($record, $data + $working);

        if ($record->exists && $record->getAttribute('status') === Status::PUBLISHED) {
            $live = self::live($record);
            $record->setRawAttributes(['draft' => self::same($data, $live) ? null : json_encode($data, self::JSON)] + $record->getAttributes());
        } else {
            self::apply($record, $data);
            $record->setRawAttributes(['draft' => null] + $record->getAttributes());
            $record->setAttribute('status', Status::DRAFT);
        }
        $record->setAttribute('updated_by', $userId ?? auth()->id());
        $record->save();

        return $record;
    }

    /** Publish the working copy: live columns, status, a revision and a new cache version. */
    public static function publish(Model $record, ?string $note = null, ?int $userId = null): Revision
    {
        $userId ??= auth()->id();

        return DB::transaction(function () use ($record, $note, $userId) {
            $before = $record->exists ? self::live($record) : [];
            $data = self::workingCopy($record);
            self::apply($record, $data);
            $record->setRawAttributes(['draft' => null] + $record->getAttributes());
            $record->setAttribute('status', Status::PUBLISHED);
            $record->setAttribute('published_at', now());
            $record->setAttribute('publish_at', null);
            $record->setAttribute('updated_by', $userId ?? $record->getAttribute('updated_by'));
            $record->save();

            Slugs::afterPublish($record, $before, self::live($record));

            $revision = $record->revisions()->create([
                'snapshot' => self::live($record),
                'note' => $note ?? 'published',
                'user_id' => $userId,
            ]);
            ContentCache::bump();

            return $revision;
        });
    }

    /** Take a published record off the site (it keeps its content and revisions). */
    public static function unpublish(Model $record, ?int $userId = null): void
    {
        $record->setAttribute('status', Status::DRAFT);
        $record->setAttribute('publish_at', null);
        $record->setAttribute('updated_by', $userId ?? auth()->id());
        $record->save();
        ContentCache::bump();
    }

    public static function discard(Model $record, ?int $userId = null): void
    {
        $record->setRawAttributes(['draft' => null] + $record->getAttributes());
        $record->setAttribute('updated_by', $userId ?? auth()->id());
        $record->save();
    }

    public static function schedule(Model $record, ?CarbonInterface $at, ?int $userId = null): void
    {
        $record->setAttribute('publish_at', $at?->copy()->utc());
        $record->setAttribute('updated_by', $userId ?? auth()->id());
        $record->save();
    }

    /** A revision becomes the record's new draft (brief §8.5: restoring creates a new draft). */
    public static function restore(Model $record, Revision $revision, ?int $userId = null): void
    {
        if ($revision->revisionable_id !== (string) $record->getKey()) {
            throw new InvalidArgumentException('The revision belongs to another record.');
        }
        $snapshot = array_intersect_key((array) $revision->snapshot, Registry::fields($record));
        self::saveDraft($record, $snapshot, $userId);
    }

    /**
     * Publish every record whose scheduled time has come (App\Jobs\PublishScheduledContent).
     *
     * @return int how many were published
     */
    public static function publishDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $count = 0;
        foreach (Registry::models() as $class) {
            $class::query()->whereNotNull('publish_at')->where('publish_at', '<=', $now->copy()->utc())->get()
                ->each(function (Model $record) use (&$count) {
                    self::publish($record, 'scheduled', $record->getAttribute('updated_by'));
                    $count++;
                });
        }

        return $count;
    }

    // ------------------------------------------------------------------ storage

    /**
     * Write values into the live columns (JSON as JSON text, as the importer writes it).
     *
     * @param  array<string, mixed>  $data
     */
    public static function apply(Model $record, array $data): void
    {
        $raw = [];
        $items = null;
        foreach (Registry::fields($record) as $column => $kind) {
            if (! array_key_exists($column, $data)) {
                continue;
            }
            $value = $data[$column];
            if ($kind === Registry::ITEMS) {
                $items = (array) $value;

                continue;
            }
            $raw[$column] = match ($kind) {
                Registry::PLAIN => is_bool($value) ? (int) $value : $value,
                default => $value === null ? null : json_encode($value, self::JSON),
            };
        }
        $record->setRawAttributes($raw + $record->getAttributes());
        if ($items !== null && $record instanceof NavigationMenu) {
            if (! $record->exists) {
                $record->save();
            }
            self::syncMenuItems($record, $items);
        }
    }

    /**
     * Values as they are stored: types of the plain columns, empty language texts left out (a missing translation is
     * absent, never ""), lists re-indexed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(Model $record, array $data): array
    {
        $out = [];
        foreach (Registry::fields($record) as $column => $kind) {
            if (! array_key_exists($column, $data)) {
                continue;
            }
            $value = $data[$column];
            $out[$column] = match ($kind) {
                Registry::PLAIN => self::plain($column, $value),
                Registry::TRANSLATABLE => self::translations($value),
                Registry::TREE => is_array($value) ? self::tree($value) : null,
                Registry::ITEMS => array_values(array_map(fn ($item) => self::item((array) $item), (array) $value)),
                default => is_array($value) ? self::lists($value) : (($value === '' || $value === null) ? null : $value),
            };
        }

        return $out;
    }

    /**
     * The form's value without the empty keys it added: a form built for many shapes (App\Filament\Cms\Forms\TreeFields)
     * gives an item every key any item has; a key the stored item did not have and the editor left empty is dropped,
     * so an unchanged form saves exactly the stored structure.
     */
    public static function prune(mixed $edited, mixed $origin): mixed
    {
        if (! is_array($edited) || ! is_array($origin)) {
            return $edited;
        }
        if (array_is_list($edited)) {
            $byId = [];
            foreach ($origin as $item) {
                if (is_array($item) && isset($item['id']) && is_scalar($item['id'])) {
                    $byId[(string) $item['id']] = $item;
                }
            }
            $out = [];
            foreach ($edited as $i => $item) {
                $match = is_array($item) && isset($item['id']) && is_scalar($item['id']) && isset($byId[(string) $item['id']])
                    ? $byId[(string) $item['id']]
                    : (array_is_list($origin) && array_key_exists($i, $origin) ? $origin[$i] : null);
                $out[] = $match !== null ? self::prune($item, $match) : $item;
            }

            return $out;
        }
        $out = [];
        foreach ($edited as $key => $value) {
            if (! array_key_exists($key, $origin)) {
                if (self::isEmpty($value)) {
                    continue;
                }
                $out[$key] = $value;

                continue;
            }
            $out[$key] = self::prune($value, $origin[$key]);
        }

        return $out;
    }

    private static function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (! self::isEmpty($v)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || $value === '' || $value === false;
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    public static function same(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);

        return json_encode($a, self::JSON) === json_encode($b, self::JSON);
    }

    public static function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private static function plain(string $column, mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }
        if (in_array($column, self::BOOLEANS, true)) {
            return (bool) $value;
        }
        if (in_array($column, self::INTEGERS, true)) {
            return $value === null || $value === '' ? null : (int) $value;
        }
        if ($column === 'effective_from' && is_string($value) && strlen($value) > 10) {
            return substr($value, 0, 10);
        }

        return $value === '' ? null : $value;
    }

    /** {en, ar, ru} with empty languages left out; null when no language has a value. */
    private static function translations(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            if (! array_key_exists($locale, $value)) {
                continue;
            }
            $text = is_array($value[$locale]) ? self::lists($value[$locale]) : $value[$locale];
            if ($text === null || $text === '' || $text === []) {
                continue;
            }
            $out[$locale] = $text;
        }
        foreach ($value as $key => $text) {
            if (! in_array($key, Tree::LOCALES, true) && ! is_int($key)) {
                $out[$key] = $text; // never drop what the form did not know
            }
        }

        return $out === [] ? null : $out;
    }

    /** A shared tree: leaves {en, ar, ru} without empty languages (a leaf keeps at least its key set). */
    private static function tree(array $node): array
    {
        if (self::looksLikeLeaf($node)) {
            $leaf = array_filter($node, fn ($v) => $v !== null && $v !== '');

            return $leaf === [] ? ['en' => ''] : $leaf;
        }
        $out = [];
        foreach ($node as $key => $child) {
            $out[$key] = is_array($child) ? self::tree($child) : $child;
        }

        return array_is_list($node) ? array_values($out) : $out;
    }

    /** A leaf of the form: only language keys with scalar (or null) values. */
    private static function looksLikeLeaf(array $node): bool
    {
        if ($node === [] || array_is_list($node) || array_diff(array_keys($node), Tree::LOCALES) !== []) {
            return false;
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private static function lists(array $value): array
    {
        $out = [];
        foreach ($value as $key => $child) {
            $out[$key] = is_array($child) ? self::lists($child) : $child;
        }

        return array_is_list($value) ? array_values($out) : $out;
    }

    // ------------------------------------------------------------------ navigation menus

    /** @return list<array<string, mixed>> */
    private static function menuItems(Model $menu): array
    {
        if (! $menu instanceof NavigationMenu || ! $menu->exists) {
            return [];
        }

        return NavigationItem::query()->where('menu_id', $menu->getKey())->orderBy('position')->get()
            ->map(fn (NavigationItem $item) => self::item([
                'id' => $item->getKey(),
                'link_key' => $item->link_key,
                'label' => self::decode($item->getAttributes()['label'] ?? null),
                'registry_label' => self::decode($item->getAttributes()['registry_label'] ?? null),
                'target_type' => $item->target_type,
                'target' => $item->target,
                'new_tab' => $item->new_tab,
                'enabled' => $item->enabled,
                'options' => self::decode($item->getAttributes()['options'] ?? null),
            ]))->values()->all();
    }

    /** @param array<string, mixed> $item */
    private static function item(array $item): array
    {
        return [
            'id' => isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null,
            'link_key' => ($item['link_key'] ?? null) ?: null,
            'label' => self::translations($item['label'] ?? null),
            'registry_label' => self::translations($item['registry_label'] ?? null),
            'target_type' => (string) ($item['target_type'] ?? 'url'),
            'target' => ($item['target'] ?? null) === '' ? null : ($item['target'] ?? null),
            'new_tab' => (bool) ($item['new_tab'] ?? false),
            'enabled' => (bool) ($item['enabled'] ?? true),
            'options' => is_array($item['options'] ?? null) && $item['options'] !== [] ? $item['options'] : null,
        ];
    }

    /**
     * The menu's items become exactly the published list: existing ids are updated in place (their link keys never
     * change), new ones created, missing ones deleted.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private static function syncMenuItems(NavigationMenu $menu, array $items): void
    {
        $existing = NavigationItem::withTrashed()->where('menu_id', $menu->getKey())->get()->keyBy('id');
        $keep = [];
        foreach (array_values($items) as $position => $item) {
            $item = self::item($item);
            $model = $item['id'] !== null && $existing->has($item['id']) ? $existing[$item['id']] : new NavigationItem(['menu_id' => $menu->getKey()]);
            $attributes = [
                'label' => $item['label'] === null ? null : json_encode($item['label'], self::JSON),
                'target_type' => $item['target_type'],
                'target' => $item['target'],
                'new_tab' => (int) $item['new_tab'],
                'enabled' => (int) $item['enabled'],
                'position' => $position + 1,
                'options' => $item['options'] === null ? null : json_encode($item['options'], self::JSON),
                'status' => Status::PUBLISHED,
                'deleted_at' => null,
            ];
            if (! $model->exists) {
                $attributes['menu_id'] = $menu->getKey();
                $attributes['link_key'] = $item['link_key'];
                $attributes['registry_label'] = $item['registry_label'] === null ? null : json_encode($item['registry_label'], self::JSON);
                $attributes['copy_source'] = $item['link_key'] !== null ? 'docx' : 'pending';
            }
            $model->setRawAttributes($attributes + $model->getAttributes());
            $model->setAttribute('updated_by', auth()->id() ?? $model->getAttribute('updated_by'));
            $model->save();
            $keep[] = $model->getKey();
        }
        foreach ($existing as $id => $model) {
            if (! in_array($id, $keep, true)) {
                $model->forceDelete();
            }
        }
    }
}
