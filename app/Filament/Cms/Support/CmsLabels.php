<?php

namespace App\Filament\Cms\Support;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Status;
use App\Filament\Support\EnquiryStats;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Labels, colours and icons of the CMS states (admin interface language, lang/{locale}/cms.php). */
final class CmsLabels
{
    public static function locale(string $locale): string
    {
        return (string) __('cms.locale.'.$locale);
    }

    /** draft | published | changes | scheduled | trashed */
    public static function state(Model $record): string
    {
        return match (true) {
            method_exists($record, 'trashed') && $record->trashed() => 'trashed',
            $record->getAttribute('publish_at') !== null => 'scheduled',
            $record->getAttribute('status') !== Status::PUBLISHED => 'draft',
            Publisher::hasDraft($record) => 'changes',
            default => 'published',
        };
    }

    public static function stateLabel(string $state): string
    {
        return (string) __('cms.status.'.$state);
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            'published' => 'success',
            'changes', 'scheduled' => 'warning',
            'trashed' => 'danger',
            default => 'gray',
        };
    }

    public static function stateIcon(string $state): Heroicon
    {
        return match ($state) {
            'published' => Heroicon::CheckCircle,
            'changes' => Heroicon::PencilSquare,
            'scheduled' => Heroicon::Clock,
            'trashed' => Heroicon::Trash,
            default => Heroicon::DocumentText,
        };
    }

    public static function copy(?string $source, bool $short = false): string
    {
        $source = in_array($source, CopySource::ALL, true) ? $source : CopySource::DOCX;

        return (string) __('cms.copy.'.$source.($short ? '_short' : ''));
    }

    public static function copyColor(?string $source): string
    {
        return match ($source) {
            CopySource::PENDING => 'warning',
            CopySource::CLIENT_APPROVED => 'success',
            default => 'gray',
        };
    }

    public static function copyIcon(?string $source): Heroicon
    {
        return match ($source) {
            CopySource::PENDING => Heroicon::ExclamationCircle,
            CopySource::CLIENT_APPROVED => Heroicon::CheckBadge,
            default => Heroicon::DocumentCheck,
        };
    }

    /** @return array<string, string> */
    public static function copyOptions(): array
    {
        $out = [];
        foreach (CopySource::ALL as $source) {
            $out[$source] = self::copy($source);
        }

        return $out;
    }

    /** A UTC time shown in Dubai time. */
    public static function dubai(mixed $time, string $format = 'j M Y, H:i'): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        $time = $time instanceof \DateTimeInterface ? Carbon::instance($time) : Carbon::parse((string) $time, 'UTC');

        return $time->copy()->setTimezone(EnquiryStats::timezone())->translatedFormat($format);
    }

    /** A field key as a label: the dictionary of lang/{locale}/cms.php "keys", else "Key words". */
    public static function key(string $key): string
    {
        $label = __('cms.keys.'.$key);

        return is_string($label) && $label !== 'cms.keys.'.$key ? $label : Str::ucfirst(str_replace(['_', '-'], ' ', $key));
    }
}
