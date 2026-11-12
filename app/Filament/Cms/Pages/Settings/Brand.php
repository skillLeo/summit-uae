<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Forms\MediaPicker;
use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Support\AdminPalette;
use App\Settings\BrandSettings;
use App\Settings\SeoSettings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Brand (docs/briefs/design-2.1-and-admin-cms.md §12.3, §3.8; App\Settings\BrandSettings): the header lockup, the
 * mark, the footer lockup, the favicon and the default social image, each a pick from the Media Library (empty =
 * today's file), and the accent colour (super admin only) with automatic contrast checks: saving is blocked unless
 * white text on it reaches 4.5:1 (buttons) and it reaches 3:1 against white and against the light band (controls).
 * "Reset to default" returns to bronze #8A6A2E, stored as null.
 *
 * Wired: the accent replaces the admin panel's primary colour (App\Filament\Support\AdminPalette) when it is set. The
 * images are stored only: the public layouts keep today's files (they are Design 2 views). The default social image
 * is the SEO screen's seo.default_og_image (linkedSettings()), one value for both screens.
 */
class Brand extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_brand';

    protected static string $settings = BrandSettings::class;

    protected static ?string $slug = 'settings/brand';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 70;

    public const DEFAULT_ACCENT = '#8A6A2E';

    /** The minimum ratios of §12.3: text 4.5:1, controls 3:1. */
    public const CHECKS = [
        'text' => ['fg' => '#FFFFFF', 'bg' => 'accent', 'min' => 4.5],
        'control' => ['fg' => 'accent', 'bg' => '#FFFFFF', 'min' => 3.0],
        'band' => ['fg' => 'accent', 'bg' => 'band', 'min' => 3.0],
    ];

    /** Today's file per image (null = this file). */
    private const TODAY = [
        'header_lockup' => 'public/images/d2/brand/lockup@1x.png',
        'mark' => 'resources/brand/emblem.svg',
        'footer_lockup' => 'resources/brand/lockup.svg',
        'favicon' => 'public/favicon.ico',
        'social_image' => 'public/images/og-default.png',
    ];

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_brand.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_brand.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_brand.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $picker = fn (string $name) => MediaPicker::make($name)
            ->label((string) __("cms_site.settings_brand.{$name}"))
            ->placeholder((string) __('cms_site.settings_brand.today'))
            ->helperText(new HtmlString(e((string) __("cms_site.settings_brand.{$name}_hint")).' '
                .e((string) __('cms_site.settings_brand.today_file')).' <bdi dir="ltr">'.e(self::TODAY[$name]).'</bdi>'));

        $superAdmin = CmsAccess::superAdmin();

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_brand.logos'))
                ->description((string) __('cms_site.settings_brand.logos_hint'))
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        $picker('header_lockup'),
                        $picker('mark'),
                        $picker('footer_lockup'),
                        $picker('favicon'),
                    ]),
                ]),
            Section::make((string) __('cms_site.settings_brand.social'))
                ->description((string) __('cms_site.settings_brand.social_hint'))
                ->schema([
                    $picker('social_image'),
                ]),
            Section::make((string) __('cms_site.settings_brand.accent'))
                ->description((string) __($superAdmin ? 'cms_site.settings_brand.accent_hint' : 'cms_site.settings_brand.accent_locked'))
                ->schema([
                    TextInput::make('accent')->label((string) __('cms_site.settings_brand.accent_field'))
                        ->helperText(new HtmlString(str_replace('%default%', '<bdi dir="ltr">'.self::DEFAULT_ACCENT.'</bdi>',
                            e((string) __('cms_site.settings_brand.accent_field_hint', ['default' => '%default%'])))))
                        ->placeholder(self::DEFAULT_ACCENT)
                        ->maxLength(7)
                        ->regex('/^#[0-9A-Fa-f]{6}$/')
                        ->validationMessages(['regex' => (string) __('cms_site.settings_brand.errors.format')])
                        ->live(debounce: 400)
                        ->disabled(! $superAdmin)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
                                return;
                            }
                            foreach (self::checks($value) as $check) {
                                if (! $check['pass']) {
                                    $fail((string) __('cms_site.settings_brand.errors.contrast', [
                                        'pair' => (string) __('cms_site.settings_brand.check.'.$check['key']),
                                        'ratio' => self::ratioLabel($check['ratio']),
                                        'min' => number_format($check['min'], 1).':1',
                                    ]));

                                    return;
                                }
                            }
                        })
                        ->hintAction(Action::make('resetAccent')
                            ->label((string) __('cms_site.settings_brand.reset'))
                            ->color('primary')
                            ->visible($superAdmin && $this->canEdit())
                            ->action(fn (Set $set) => $set('accent', null)))
                        ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false', 'autocomplete' => 'off']),
                    Text::make(fn (Get $get): HtmlString => self::checksHtml($get('accent')))
                        ->extraAttributes(['class' => 'cms-settings-contrast', 'aria-live' => 'polite']),
                ]),
        ]);
    }

    /** WCAG 2.2 relative luminance of "#RRGGBB". */
    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return list<array{key: string, ratio: float, min: float, pass: bool}> the required pairs of the accent */
    public static function checks(string $accent): array
    {
        $resolve = fn (string $colour): string => match ($colour) {
            'accent' => $accent,
            'band' => AdminPalette::token('band'),
            default => $colour,
        };
        $out = [];
        foreach (self::CHECKS as $key => $check) {
            $ratio = self::contrast($resolve($check['fg']), $resolve($check['bg']));
            $out[] = ['key' => $key, 'ratio' => $ratio, 'min' => $check['min'], 'pass' => $ratio >= $check['min']];
        }

        return $out;
    }

    /** "5.02:1", rounded down so a ratio shown as passing really passes. */
    public static function ratioLabel(float $ratio): string
    {
        return number_format(floor($ratio * 100) / 100, 2).':1';
    }

    /** The live contrast lines under the accent field, with a sample of each pair. */
    private static function checksHtml(mixed $value): HtmlString
    {
        $accent = is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : null;
        $shown = $accent ?? self::DEFAULT_ACCENT;
        if (filled($value) && $accent === null) {
            return new HtmlString('<p class="cms-settings-contrast__wait">'.e((string) __('cms_site.settings_brand.contrast_wait')).'</p>');
        }

        $rows = '';
        foreach (self::checks($shown) as $check) {
            $sample = match ($check['key']) {
                'text' => '<span class="cms-settings-contrast__sample" style="background-color: '.$shown.'; color: #FFFFFF;">Aa</span>',
                'control' => '<span class="cms-settings-contrast__sample cms-settings-contrast__sample--ring" style="border-color: '.$shown.'; color: '.$shown.';">Aa</span>',
                default => '<span class="cms-settings-contrast__sample cms-settings-contrast__sample--band" style="border-color: '.$shown.'; color: '.$shown.';">Aa</span>',
            };
            $rows .= '<li class="cms-settings-contrast__row" data-pass="'.($check['pass'] ? 'true' : 'false').'">'.$sample
                .'<span class="cms-settings-contrast__text"><span class="cms-settings-contrast__pair">'.e((string) __('cms_site.settings_brand.check.'.$check['key'])).'</span>'
                .'<span class="cms-settings-contrast__figures"><span class="cms-settings-contrast__ratio" dir="ltr">'.e(self::ratioLabel($check['ratio'])).'</span> '
                .'<span class="cms-settings-contrast__min">'.e((string) __('cms_site.settings_brand.needs', ['min' => number_format($check['min'], 1).':1'])).'</span></span></span>'
                .'<span class="cms-settings-contrast__result">'.e((string) __($check['pass'] ? 'cms_site.settings_brand.pass' : 'cms_site.settings_brand.fail')).'</span></li>';
        }
        $caption = $accent === null
            ? (string) __('cms_site.settings_brand.contrast_default', ['default' => '%colour%'])
            : (string) __('cms_site.settings_brand.contrast_for', ['colour' => '%colour%']);
        $caption = str_replace('%colour%', '<bdi dir="ltr">'.e($shown).'</bdi>', e($caption));

        return new HtmlString('<p class="cms-settings-contrast__caption">'.$caption.'</p><ul class="cms-settings-contrast__list">'.$rows.'</ul>');
    }

    protected function linkedSettings(): array
    {
        return ['social_image' => [SeoSettings::class, 'default_og_image']];
    }

    protected function fieldLabels(): array
    {
        return ['accent' => (string) __('cms_site.settings_brand.accent_field')];
    }

    protected function formToSettings(array $data): array
    {
        $uuid = fn (string $name): ?string => filled($data[$name] ?? null) ? (string) $data[$name] : null;
        $accent = is_string($data['accent'] ?? null) ? strtoupper(trim($data['accent'])) : null;
        if ($accent === '' || $accent === self::DEFAULT_ACCENT) {
            $accent = null; // the default is stored as null
        }
        if (! CmsAccess::superAdmin()) {
            $accent = app(BrandSettings::class)->accent; // the accent belongs to the super admin
        }

        return [
            'header_lockup' => $uuid('header_lockup'),
            'mark' => $uuid('mark'),
            'footer_lockup' => $uuid('footer_lockup'),
            'favicon' => $uuid('favicon'),
            'accent' => $accent,
            'social_image' => $uuid('social_image'),
        ];
    }
}
