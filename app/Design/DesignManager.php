<?php

namespace App\Design;

use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/**
 * Two designs on the same routes, controllers, content and form backend.
 *
 *  - Design 1 ("The File") renders from resources/views (frozen at the git tag design-1).
 *  - Design 2 searches resources/views/designs/d2 first: a Design 2 view replaces the Design 1 view
 *    of the same name, and any view Design 2 does not have yet falls back to Design 1.
 *
 * Design 2 internals are referenced through the d2:: namespace and never reuse a Design 1 view name
 * (tests/Feature/Design/ViewIsolationTest.php), so a Design 1 fallback page always renders with
 * Design 1's own layout, partials and assets.
 */
final class DesignManager
{
    public const DESIGNS = [1, 2];

    private ?int $current = null;

    /** @var list<string>|null the finder paths before any design was applied */
    private ?array $basePaths = null;

    /** @var list<string>|null config('view.paths') before any design was applied */
    private ?array $baseConfigPaths = null;

    public function __construct(private readonly Factory $views) {}

    /** A valid design number from untrusted input ("1", "2", 1, 2), otherwise null. */
    public static function normalize(mixed $value): ?int
    {
        if (is_int($value)) {
            return in_array($value, self::DESIGNS, true) ? $value : null;
        }
        if (is_string($value) && preg_match('/^\d$/', $value) === 1) {
            return self::normalize((int) $value);
        }

        return null;
    }

    public function default(): int
    {
        return self::normalize(config('design.default')) ?? 1;
    }

    public function current(): int
    {
        return $this->current ?? $this->default();
    }

    public function is(int $design): bool
    {
        return $this->current() === $design;
    }

    /** Whether SetDesign (or the error renderer) applied a design in this request. */
    public function applied(): bool
    {
        return $this->current !== null;
    }

    public function apply(int $design): void
    {
        $finder = $this->views->getFinder();
        if (! $finder instanceof FileViewFinder) {
            return;
        }
        $this->basePaths ??= array_values(array_filter(
            $finder->getPaths(),
            fn (string $p) => rtrim($p, '/') !== rtrim((string) config('design.d2.views'), '/'),
        ));

        $paths = $design === 2 ? [(string) config('design.d2.views'), ...$this->basePaths] : $this->basePaths;
        $finder->setPaths($paths);
        $finder->flush();

        // Error pages: Laravel's exception handler rebuilds the errors:: namespace from config('view.paths')
        // ({path}/errors, then its stock views) right before it renders errors::{status}
        // (RegisterErrorViewPaths), so Design 2 lists its folder first there too: designs/d2/errors/403 …
        // replace the stock pages, any status Design 2 lacks keeps the stock page. Design 1: the original list.
        $this->baseConfigPaths ??= array_values(array_filter(
            (array) config('view.paths'),
            fn ($p) => rtrim((string) $p, '/') !== rtrim((string) config('design.d2.views'), '/'),
        ));
        config(['view.paths' => $design === 2 ? [(string) config('design.d2.views'), ...$this->baseConfigPaths] : $this->baseConfigPaths]);

        $this->current = $design;
        $this->views->share('design', $design);
    }
}
