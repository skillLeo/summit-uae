<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Inline SVG icon from resources/icons/{name}.svg (24 grid, 1.5 stroke, currentColor).
 *
 *     <x-icon name="globe" />                         1em square, decorative
 *     <x-icon name="phone" size="24" class="x" />     extra attributes and classes are merged
 *
 * Directional icons also get "icon--mirror"; the stylesheet flips them when the text runs
 * right to left. Grid, stroke and mirroring rules: docs/icons.md.
 */
class Icon extends Component
{
    /** Icons that follow the line direction and are mirrored in RTL (documented in docs/icons.md). */
    public const MIRRORED = ['chevron-start', 'chevron-end', 'external', 'faq', 'contact'];

    /** File-read cache shared by every instance: name => parsed file, or null when missing. */
    private static array $cache = [];

    /** Default <svg> attributes: the file's own, then size and accessibility defaults. */
    public array $defaults = [];

    /** Inner markup of the icon file. */
    public string $body = '';

    public bool $mirrored = false;

    /** Static helpers are not view data. */
    protected $except = ['names'];

    public function __construct(public string $name, public string|int|float|null $size = null)
    {
        $icon = self::load($name);

        if ($icon === null) {
            return;
        }

        $dimension = $size === null || $size === '' ? '1em' : (string) $size;

        $this->body = $icon['body'];
        $this->mirrored = in_array($name, self::MIRRORED, true);
        $this->defaults = $icon['attributes'] + [
            'width' => $dimension,
            'height' => $dimension,
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ];
    }

    public function shouldRender(): bool
    {
        return $this->body !== '';
    }

    public function render(): View
    {
        return view('components.icon');
    }

    /**
     * Every available icon name, sorted (for the styleguide).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = array_map(
            static fn (string $path): string => basename($path, '.svg'),
            glob(resource_path('icons/*.svg')) ?: [],
        );
        sort($names);

        return $names;
    }

    /**
     * Read and split an icon file once per request/worker.
     *
     * @return array{attributes: array<string, string>, body: string}|null
     */
    private static function load(string $name): ?array
    {
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        $path = resource_path("icons/{$name}.svg");
        $svg = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) === 1 && is_file($path)
            ? file_get_contents($path)
            : false;

        if ($svg === false || preg_match('/<svg\b([^>]*)>(.*)<\/svg>/s', $svg, $parts) !== 1) {
            if (! app()->isProduction()) {
                throw new InvalidArgumentException("Unknown icon [{$name}]. Icons are resources/icons/<name>.svg; see docs/icons.md.");
            }

            logger()->warning('Unknown icon requested.', ['icon' => $name]);

            return self::$cache[$name] = null;
        }

        preg_match_all('/([a-zA-Z][\w:-]*)="([^"]*)"/', $parts[1], $pairs, PREG_SET_ORDER);

        $attributes = [];
        foreach ($pairs as [, $key, $value]) {
            // Inline SVG needs no namespace; size always comes from the component or CSS.
            if (! in_array($key, ['xmlns', 'width', 'height'], true)) {
                $attributes[$key] = $value;
            }
        }

        return self::$cache[$name] = ['attributes' => $attributes, 'body' => trim($parts[2])];
    }
}
