<?php

namespace App\Filament\Resources\MediaLibrary\Forms;

use App\Content\Cms\MediaFiles;
use Closure;
use Filament\Forms\Components\Field;

/**
 * The focal point of an image (docs/briefs/design-2.1-and-admin-cms.md §8.9): [x, y] in % of the image, used as
 * object-position (library images) or as the eye-line point of a team portrait (App\Design\D2\Portraits).
 *
 * The image is one click target (a button: a click sets the point; the arrow keys move it by 1 %, with Shift by 5 %),
 * a marker shows the point, and two number fields (x, y) set it exactly. "Clear" removes it (state null). Alpine in
 * the Blade view, no external library. The state is [x, y] with one decimal, or null.
 */
final class FocalPointPicker extends Field
{
    protected string $view = 'filament.media.focal-point';

    protected string|Closure|null $imageUrl = null;

    protected string|Closure|null $emptyText = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(null);
        $this->afterStateHydrated(function (FocalPointPicker $component, mixed $state): void {
            $component->state(MediaFiles::point($state));
        });
        $this->dehydrateStateUsing(fn (mixed $state) => MediaFiles::point($state));
        $this->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === [] || $value === '') {
                return;
            }
            if (MediaFiles::point($value) === null) {
                $fail((string) __('cms_media.focal.invalid'));
            }
        });
    }

    /** The image the point is chosen on (null: only the number fields). */
    public function imageUrl(string|Closure|null $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        $url = $this->evaluate($this->imageUrl);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function emptyText(string|Closure|null $text): static
    {
        $this->emptyText = $text;

        return $this;
    }

    public function getEmptyText(): ?string
    {
        $text = $this->evaluate($this->emptyText);

        return is_string($text) ? $text : null;
    }
}
