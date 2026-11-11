<?php

namespace App\Console\Commands;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\MediaFiles;
use App\Content\Cms\Tree;
use App\Models\Content\TeamMember;
use App\Models\Content\UiString;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fills the Media Library from today's public files (docs/briefs/design-2.1-and-admin-cms.md §8.8 step 2, §8.9), with
 * their alt text per language:
 *   - team portraits: the largest JPEG of each member (public/images/team/<portrait>-<width>.jpg), attached to the
 *     member's record (collection "photo", source = import), alt = team_members.alt, focal point = team_members.focal_point;
 *   - the brand files (public/images/d2/brand/*): Media Library assets of the collection "brand", alt = the logo's alt
 *     text the header uses (ui brand.logo_alt, else brand.name);
 *   - the default social image (public/images/og-default.png): collection "social", alt = the brand name.
 *
 * public/ is only read: the library stores cleaned copies, and the public site keeps rendering the static files (an
 * imported team photo is not an upload, so <x-d2.team.portrait> keeps its static files). Idempotent: a file imported
 * once (import_key / custom property import_path) is skipped, so a second run creates nothing.
 */
final class MediaImportCommand extends Command
{
    protected $signature = 'summit:media-import';

    protected $description = 'Import the team portraits, brand files and default social image into the media library (idempotent)';

    /** @var array{created: int, skipped: int, failed: int} */
    private array $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];

    public function handle(): int
    {
        $this->counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $this->team();
        $brandAlt = $this->uiText('brand.logo_alt') ?? $this->uiText('brand.name');
        foreach (glob(public_path('images/d2/brand/*')) ?: [] as $path) {
            if (in_array(MediaFiles::mime($path), MediaFiles::IMAGES, true)) {
                $this->asset($path, 'brand', $brandAlt);
            }
        }
        $og = public_path('images/og-default.png');
        if (is_file($og)) {
            $this->asset($og, 'social', $this->uiText('brand.name'));
        }

        $this->table(['Created', 'Already imported', 'Failed'], [[$this->counts['created'], $this->counts['skipped'], $this->counts['failed']]]);

        return $this->counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function team(): void
    {
        foreach (TeamMember::query()->orderBy('position')->get() as $member) {
            $live = Publisher::live($member);
            $stem = is_string($live['portrait'] ?? null) ? basename($live['portrait']) : '';
            if ($stem === '') {
                continue; // no photo supplied yet (photo pending)
            }
            $path = $this->largestJpeg($stem);
            if ($path === null) {
                $this->warn("{$member->getKey()}: no JPEG in public/images/team for {$stem}.");

                continue;
            }
            $key = $this->relative($path);
            $exists = $member->media()->where('collection_name', TeamMember::PHOTO)->get()
                ->contains(fn ($m) => $m->getCustomProperty('import_path') === $key);
            if ($exists) {
                $this->counts['skipped']++;

                continue;
            }
            $this->attempt($key, fn () => MediaFiles::add($member, $path, TeamMember::PHOTO, (string) $stem, basename($path), [
                'source' => MediaFiles::SOURCE_IMPORT,
                'import_path' => $key,
                'copy_source' => (string) ($member->copy_source ?: CopySource::DOCX),
                'alt' => $this->locales($live['alt'] ?? null),
                'focal_point' => MediaFiles::point($live['focal_point'] ?? null),
            ]));
        }
    }

    /** @param array<string, string>|null $alt */
    private function asset(string $path, string $collection, ?array $alt): void
    {
        $key = $this->relative($path);
        if (MediaAsset::withTrashed()->where('import_key', $key)->exists()) {
            $this->counts['skipped']++;

            return;
        }
        $this->attempt($key, function () use ($path, $collection, $alt, $key) {
            $asset = MediaAsset::query()->create([
                'name' => basename($path),
                'collection' => $collection,
                'copy_source' => CopySource::DOCX,
                'import_key' => $key,
            ]);
            try {
                MediaFiles::add($asset, $path, MediaAsset::FILE, basename($path), basename($path), [
                    'source' => MediaFiles::SOURCE_IMPORT,
                    'import_path' => $key,
                    'copy_source' => CopySource::DOCX,
                    'alt' => $alt ?? array_fill_keys(Tree::LOCALES, ''),
                ]);
            } catch (Throwable $e) {
                $asset->forceDelete();

                throw $e;
            }
        });
    }

    private function attempt(string $key, callable $add): void
    {
        try {
            $add();
            $this->counts['created']++;
            $this->line("Imported {$key}");
        } catch (Throwable $e) {
            $this->counts['failed']++;
            $this->error("{$key}: {$e->getMessage()}");
        }
    }

    private function largestJpeg(string $stem): ?string
    {
        $best = null;
        $width = 0;
        foreach (glob(public_path('images/team/'.$stem.'-*.jpg')) ?: [] as $file) {
            if (preg_match('/-(\d+)\.jpg$/', $file, $m) && (int) $m[1] > $width) {
                $width = (int) $m[1];
                $best = $file;
            }
        }

        return $best;
    }

    /** @return array<string, string>|null the interface text in every language (published rows, else the lang files) */
    private function uiText(string $key): ?array
    {
        $row = UiString::query()->where('group', 'ui')->where('key', $key)->first();
        $text = $row !== null ? (array) (Publisher::live($row)['text'] ?? []) : [];
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            $value = $text[$locale] ?? null;
            if (! is_string($value) || $value === '') {
                $value = content()->ui($key, $locale);
            }
            $out[$locale] = (string) $value;
        }

        return array_filter($out) === [] ? null : $out;
    }

    /** @return array<string, string> */
    private function locales(mixed $value): array
    {
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            $out[$locale] = is_array($value) && is_string($value[$locale] ?? null) ? $value[$locale] : '';
        }

        return $out;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(public_path(), '', $path), '/');
    }
}
