<?php

namespace App\Content\Cms;

use App\Models\Content\TeamMember;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The media of the CMS (docs/briefs/design-2.1-and-admin-cms.md §8.9), read by the public components and written by the
 * Media Library, the Team screen and summit:media-import.
 *
 * Files: images (JPEG, PNG, WebP, AVIF) and PDF downloads, at most 10 MB. Every image is cleaned before it is stored:
 * EXIF, XMP, IPTC and comments are removed (JPEG, PNG and WebP losslessly, by dropping those segments or chunks; AVIF,
 * and a JPEG whose EXIF orientation has to be applied, by a GD re-encode), so the original on the disk carries no metadata.
 *
 * Conversions: WebP and AVIF at 480, 768, 1200 and 1600 px wide ("webp-480" … "avif-1600"), never upscaled: a width the
 * image does not reach is made once at the image's own width (the next one up is left out), so srcset lists real
 * widths only. Team photos are cut to the 4:5 portrait frame (from the centre); library images keep their shape.
 *
 * Custom properties of a media row:
 *   alt          {en, ar, ru}   the alt text per language (library images; team photos use team_members.alt)
 *   focal_point  [x, y]         % of the image, used as object-position (team photos: team_members.focal_point)
 *   copy_source  docx | pending | client_approved
 *   source       upload | import
 *   width, height               of the stored (cleaned) original
 *
 * The image-and-text block and og_image keep a media UUID (App\Filament\Cms\Forms\MediaPicker).
 */
final class MediaFiles
{
    public const WIDTHS = [480, 768, 1200, 1600];

    public const FORMATS = ['avif', 'webp'];

    public const IMAGES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public const ACCEPTED = [...self::IMAGES, 'application/pdf'];

    /** 10 MB (brief §8.9), in kilobytes as Filament's maxSize() takes it. */
    public const MAX_KB = 10240;

    /** The team portrait frame: height = width × 1.25 (4:5). */
    public const PORTRAIT = 1.25;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_IMPORT = 'import';

    private const QUALITY = ['avif' => 60, 'webp' => 82];

    // ------------------------------------------------------------------ reading (public components)

    /** The media row of a UUID; null when unknown or when its library asset is in the Trash. */
    public static function byUuid(?string $uuid): ?Media
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }
        $media = Media::query()->where('uuid', $uuid)->first();
        if ($media === null) {
            return null;
        }
        if ($media->model_type === (new MediaAsset)->getMorphClass()
            && ! MediaAsset::query()->whereKey($media->model_id)->exists()) {
            return null; // the asset is in the Trash (or gone)
        }

        return $media;
    }

    /** The URL of a media file or one of its conversions ("" = the original; a missing conversion falls back to the nearest one made). */
    public static function url(?string $uuid, string $conversion = ''): ?string
    {
        $media = self::byUuid($uuid);

        return $media === null ? null : self::mediaUrl($media, $conversion);
    }

    /** "url 480w, url 768w …" of one format (avif | webp); "" for a file without conversions. */
    public static function srcset(?string $uuid, string $format = 'webp'): string
    {
        $media = self::byUuid($uuid);

        return $media === null ? '' : self::mediaSrcset($media, $format);
    }

    /** The alt text in a language (no fallback to another language: a missing alt text is an empty one). */
    public static function alt(?string $uuid, string $locale): string
    {
        $media = self::byUuid($uuid);
        $alt = $media?->getCustomProperty('alt');

        return is_array($alt) && is_string($alt[$locale] ?? null) ? trim($alt[$locale]) : '';
    }

    /** @return array{0: int|float, 1: int|float}|null the focal point [x, y] in % */
    public static function focus(?string $uuid): ?array
    {
        $media = self::byUuid($uuid);

        return $media === null ? null : self::point($media->getCustomProperty('focal_point'));
    }

    /** "x% y%" for object-position, or null. */
    public static function objectPosition(?string $uuid): ?string
    {
        $focus = self::focus($uuid);

        return $focus === null ? null : self::number((float) $focus[0]).'% '.self::number((float) $focus[1]).'%';
    }

    public static function mediaUrl(Media $media, string $conversion = ''): string
    {
        if ($conversion !== '' && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = self::nearest($media, $conversion);
        }
        $url = $media->getUrl($conversion);
        $base = rtrim((string) config('app.url'), '/');

        // Same-site files are printed root-relative like the static images (/images/…), whatever host serves the page.
        return $base !== '' && str_starts_with($url, $base.'/') ? substr($url, strlen($base)) : $url;
    }

    public static function mediaSrcset(Media $media, string $format = 'webp'): string
    {
        $out = [];
        foreach (self::sizes($media, self::isPortrait($media)) as $name => [$width]) {
            $conversion = $format.'-'.$name;
            if ($media->hasGeneratedConversion($conversion)) {
                $out[] = self::mediaUrl($media, $conversion).' '.$width.'w';
            }
        }

        return implode(', ', $out);
    }

    /**
     * The conversions an image gets: conversion width => [actual width, actual height|null].
     *
     * @return array<int, array{0: int, 1: int|null}>
     */
    public static function sizes(?Media $media, bool $portrait): array
    {
        $w = (int) ($media?->getCustomProperty('width') ?? 0);
        $h = (int) ($media?->getCustomProperty('height') ?? 0);
        $out = [];
        if ($w <= 0 || $h <= 0) {
            foreach (self::WIDTHS as $width) {
                $out[$width] = [$width, $portrait ? (int) round($width * self::PORTRAIT) : null];
            }

            return $out;
        }
        $max = $portrait ? min($w, (int) floor($h / self::PORTRAIT)) : $w;
        foreach (self::WIDTHS as $width) {
            $actual = min($width, $max);
            $out[$width] = [$actual, $portrait ? (int) round($actual * self::PORTRAIT) : (int) round($actual * $h / $w)];
            if ($width >= $max) {
                break; // never upscale: the image's own width is the last one
            }
        }

        return $out;
    }

    /** The uploaded team photo of a member (custom property source = upload), the latest one; null keeps the static files. */
    public static function teamPhoto(string $code): ?Media
    {
        if ($code === '') {
            return null;
        }

        return Media::query()
            ->where('model_type', (new TeamMember)->getMorphClass())
            ->where('model_id', $code)
            ->where('collection_name', 'photo')
            ->where('custom_properties', 'like', '%"source":"'.self::SOURCE_UPLOAD.'"%')
            ->latest('id')
            ->first();
    }

    /** Whether a media row is an image (conversions, alt text, focal point). */
    public static function isImage(?Media $media): bool
    {
        return $media !== null && in_array($media->mime_type, self::IMAGES, true);
    }

    // ------------------------------------------------------------------ conversions

    /** Register the conversions of an owner model (MediaAsset: the image's shape; TeamMember: the 4:5 frame). */
    public static function registerConversions(HasMedia $model, ?Media $media, bool $portrait, string $collection): void
    {
        if ($media !== null && ! self::isImage($media)) {
            return; // PDFs have no conversions
        }
        foreach (self::sizes($media, $portrait) as $name => [$width, $height]) {
            foreach (self::FORMATS as $format) {
                $conversion = $model->addMediaConversion($format.'-'.$name)
                    ->format($format)
                    ->quality(self::QUALITY[$format])
                    ->performOnCollections($collection)
                    ->nonOptimized()
                    ->nonQueued();
                if ($portrait) {
                    $conversion->fit(Fit::Crop, $width, $height);
                } else {
                    $conversion->fit(Fit::Max, $width, $height);
                }
            }
        }
    }

    private static function isPortrait(Media $media): bool
    {
        return $media->model_type === (new TeamMember)->getMorphClass();
    }

    /** The made conversion of the same format closest to the wanted width (else the original). */
    private static function nearest(Media $media, string $conversion): string
    {
        [$format, $width] = array_pad(explode('-', $conversion, 2), 2, '0');
        $best = '';
        $distance = PHP_INT_MAX;
        foreach (self::WIDTHS as $w) {
            if ($media->hasGeneratedConversion($format.'-'.$w) && abs($w - (int) $width) < $distance) {
                $best = $format.'-'.$w;
                $distance = abs($w - (int) $width);
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------ writing (admin, import)

    /**
     * Add a file to a model's collection: checked (type, 10 MB), cleaned of metadata, measured, then stored with its
     * conversions. The source file is never changed or moved (a cleaned copy is stored).
     *
     * @param  Model&HasMedia  $model
     * @param  array<string, mixed>  $properties  custom properties (alt, focal_point, copy_source, source …)
     */
    public static function add(HasMedia $model, string $path, string $collection, string $name, string $fileName, array $properties = []): Media
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No file at {$path}.");
        }
        if (filesize($path) > self::MAX_KB * 1024) {
            throw new InvalidArgumentException('The file is larger than 10 MB.');
        }
        $mime = self::mime($path);
        if (! in_array($mime, self::ACCEPTED, true)) {
            throw new InvalidArgumentException("Files of type {$mime} are not accepted.");
        }
        $copy = tempnam(sys_get_temp_dir(), 'summit-media-');
        if ($copy === false || ! copy($path, $copy)) {
            throw new InvalidArgumentException('The file could not be copied.');
        }
        try {
            if (in_array($mime, self::IMAGES, true)) {
                self::clean($copy, $mime);
                $size = @getimagesize($copy);
                if ($size === false) {
                    throw new InvalidArgumentException('The image could not be read.');
                }
                $properties += ['width' => (int) $size[0], 'height' => (int) $size[1]];
            }
            $fileName = self::fileName($fileName, $mime);

            return $model->addMedia($copy)
                ->usingName($name)
                ->usingFileName($fileName)
                ->withCustomProperties($properties)
                ->toMediaCollection($collection);
        } finally {
            if (is_file($copy)) {
                @unlink($copy);
            }
        }
    }

    public static function mime(string $path): string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }

    /** A safe stored file name: lowercase ASCII words and the type's extension. */
    private static function fileName(string $fileName, string $mime): string
    {
        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        $stem = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) \Illuminate\Support\Str::ascii($stem))), '-');
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'pdf',
        };

        return ($stem === '' ? 'file' : substr($stem, 0, 80)).'.'.$ext;
    }

    /** Remove the metadata of an image file in place (EXIF, XMP, IPTC, comments, text chunks). */
    public static function clean(string $path, string $mime): void
    {
        match ($mime) {
            'image/jpeg' => self::cleanJpeg($path),
            'image/png' => self::cleanPng($path),
            'image/webp' => self::cleanWebp($path),
            default => self::reencode($path, $mime),
        };
    }

    private static function cleanJpeg(string $path): void
    {
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        }
        if ($orientation !== 1) {
            self::reencode($path, 'image/jpeg', $orientation); // apply the orientation, then the pixels alone remain

            return;
        }
        $data = (string) file_get_contents($path);
        $out = self::stripJpeg($data);
        if ($out === null || @imagecreatefromstring($out) === false) {
            self::reencode($path, 'image/jpeg');

            return;
        }
        file_put_contents($path, $out);
    }

    /** The JPEG without its APP1 (EXIF, XMP), APP3–APP15 (IPTC …; APP2 ICC and APP14 Adobe stay) and COM segments. */
    public static function stripJpeg(string $data): ?string
    {
        if (! str_starts_with($data, "\xFF\xD8")) {
            return null;
        }
        $out = "\xFF\xD8";
        $i = 2;
        $length = strlen($data);
        while ($i + 4 <= $length) {
            if ($data[$i] !== "\xFF") {
                return null;
            }
            $marker = ord($data[$i + 1]);
            if ($marker === 0xFF) {
                $i++;

                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) {
                return $out.substr($data, $i); // the scan data and everything after it
            }
            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $out .= substr($data, $i, 2);
                $i += 2;

                continue;
            }
            $size = unpack('n', substr($data, $i + 2, 2))[1];
            $segment = substr($data, $i, 2 + $size);
            $keep = match (true) {
                $marker === 0xFE => false,
                $marker === 0xE2 => str_starts_with(substr($segment, 4), 'ICC_PROFILE'),
                $marker === 0xEE => true,
                $marker >= 0xE1 && $marker <= 0xEF => false,
                default => true,
            };
            if ($keep) {
                $out .= $segment;
            }
            $i += 2 + $size;
        }

        return null;
    }

    private static function cleanPng(string $path): void
    {
        $data = (string) file_get_contents($path);
        if (! str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            self::reencode($path, 'image/png');

            return;
        }
        $out = substr($data, 0, 8);
        $i = 8;
        $length = strlen($data);
        while ($i + 12 <= $length) {
            $size = unpack('N', substr($data, $i, 4))[1];
            $type = substr($data, $i + 4, 4);
            $chunk = substr($data, $i, 12 + $size);
            if (! in_array($type, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'], true)) {
                $out .= $chunk;
            }
            $i += 12 + $size;
            if ($type === 'IEND') {
                break;
            }
        }
        if (@imagecreatefromstring($out) === false) {
            self::reencode($path, 'image/png');

            return;
        }
        file_put_contents($path, $out);
    }

    /** The WebP without its EXIF and XMP chunks (and their VP8X flags); the image data is kept as it is. */
    private static function cleanWebp(string $path): void
    {
        $data = (string) file_get_contents($path);
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            self::reencode($path, 'image/webp');

            return;
        }
        $body = '';
        $i = 12;
        $length = strlen($data);
        while ($i + 8 <= $length) {
            $type = substr($data, $i, 4);
            $size = unpack('V', substr($data, $i + 4, 4))[1];
            $chunk = substr($data, $i, 8 + $size + ($size % 2));
            if ($type === 'VP8X' && $size >= 1) {
                $chunk[8] = chr(ord($chunk[8]) & ~0x0C); // EXIF (0x08) and XMP (0x04) flags off
            }
            if (! in_array($type, ['EXIF', 'XMP '], true)) {
                $body .= $chunk;
            }
            $i += 8 + $size + ($size % 2);
        }
        $out = 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
        if (@imagecreatefromstring($out) === false) {
            self::reencode($path, 'image/webp');

            return;
        }
        file_put_contents($path, $out);
    }

    /** Decode and encode again with GD (which writes no metadata), applying an EXIF orientation first. */
    private static function reencode(string $path, string $mime, int $orientation = 1): void
    {
        try {
            $image = @imagecreatefromstring((string) file_get_contents($path));
        } catch (Throwable) {
            $image = false;
        }
        if ($image === false) {
            throw new InvalidArgumentException('The image could not be read.');
        }
        $image = match ($orientation) {
            2 => self::flip($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => self::flip($image, IMG_FLIP_VERTICAL),
            5 => self::flip(imagerotate($image, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => self::flip(imagerotate($image, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 92),
            'image/png' => imagepng($image, $path, 9),
            'image/webp' => imagewebp($image, $path, 90),
            'image/avif' => imageavif($image, $path, 70),
            default => false,
        };
        if (! $ok) {
            throw new InvalidArgumentException('The image could not be written.');
        }
    }

    private static function flip(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A valid focal point [x, y] (0 to 100 each) or null. Numbers keep their type (49.7 stays a float, 50 an integer), so
     * an unchanged point saves exactly the stored JSON; numeric strings (a number field) become numbers.
     *
     * @return array{0: int|float, 1: int|float}|null
     */
    public static function point(mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }
        [$x, $y] = array_values($value);
        if (! is_numeric($x) || ! is_numeric($y)) {
            return null;
        }
        $x = is_string($x) ? $x + 0 : $x;
        $y = is_string($y) ? $y + 0 : $y;

        return $x >= 0 && $x <= 100 && $y >= 0 && $y <= 100 ? [$x, $y] : null;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1).' MB';
        }

        return number_format(max(1, (int) round($bytes / 1024))).' KB';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
