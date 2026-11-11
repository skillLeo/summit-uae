<?php

namespace App\Providers\Filament;

use App\Filament\Http\Middleware\PrepareAdminRequest;
use App\Filament\Http\Middleware\SetAdminLocale;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\AdminBrand;
use App\Filament\Support\AdminLocale;
use App\Filament\Support\AdminPalette;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;
use Throwable;

/**
 * The internal admin panel (/admin): consultation requests and their dashboard, for staff only
 * (User::canAccessPanel → users.is_admin). Spec: docs/brief/upgrade-report.md §12 Phase F.
 *
 * Theme: resources/css/filament/admin/theme.css (Tailwind CSS v4 on Filament's theme, Design 2 tokens),
 * built by vite.admin.config.js into public/build-admin. Colours: App\Filament\Support\AdminPalette
 * (read from resources/css/d2/tokens.css). Fonts: the self-hosted Design 2 faces, no font CDN.
 * Nothing here changes the public site: the panel has its own routes, middleware, build and CSP
 * (App\Http\Middleware\SecurityHeaders::isAdminRequest()).
 */
class AdminPanelProvider extends PanelProvider
{
    /** URL path of the panel; SecurityHeaders gives this path (and Livewire's endpoints) the admin CSP. */
    public const PATH = 'admin';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(self::PATH)
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(fn (): string => AdminBrand::name())
            ->brandLogo(fn () => AdminBrand::logo())
            ->brandLogoHeight('2.5rem')
            ->favicon(AdminBrand::FAVICON)
            ->colors(AdminPalette::colors())
            ->font('D2 Sans', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css', 'build-admin')
            ->darkMode(false)
            ->topbar(false)
            ->sidebarWidth('17rem')
            ->globalSearch(false)
            ->unsavedChangesAlerts()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->userMenuItems([$this->languageActions()])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => AdminBrand::fonts())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => AdminBrand::loginLanguages())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Pages and widgets are listed, not discovered: the profile page is registered by ->profile().
            ->pages([
                Dashboard::class,
            ])
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                SetAdminLocale::class,
                PrepareAdminRequest::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function register(): void
    {
        parent::register();

        // blade-icons (installed with Filament) would register the alias <x-icon> for its own component on
        // every request, taking Design 1's <x-icon> (App\View\Components\Icon) over as soon as views are
        // recompiled ("Svg by name … not found"), and register every icon of every set as a component.
        // Filament draws its icons through the svg() factory, not through those components (its own
        // DisableBladeIconComponents middleware turns them off on panel routes), so they are off app-wide.
        config([
            'blade-icons.components.default' => null,
            'blade-icons.components.disabled' => true,
        ]);
    }

    public function boot(): void
    {
        self::withoutLivewireViteTagAttributes();
        Filament::serving(fn () => self::withoutBundledInter());
    }

    /**
     * Livewire adds data-navigate-track="reload" to every Vite <script> and <link> tag of the whole app
     * (for wire:navigate SPA mode), which would change the HTML of every public page of both designs.
     * The panel does not use SPA mode, so Livewire's two resolvers are removed again; any other
     * resolver is kept. (Laravel's Vite has no public API to unregister a tag-attribute resolver.)
     */
    public static function withoutLivewireViteTagAttributes(): void
    {
        $vite = app(Vite::class);
        $tracking = ['data-navigate-track' => 'reload'];
        // Vite wraps an attribute array in `fn () => $attributes`: match that wrapper by what it captured.
        $isTracking = static function (mixed $resolver) use ($tracking): bool {
            if (! $resolver instanceof Closure) {
                return $resolver === $tracking;
            }
            $function = new \ReflectionFunction($resolver);

            return str_ends_with((string) $function->getFileName(), 'Foundation'.DIRECTORY_SEPARATOR.'Vite.php')
                && ($function->getStaticVariables()['attributes'] ?? null) === $tracking;
        };

        try {
            Closure::bind(function () use ($isTracking): void {
                foreach (['scriptTagAttributesResolvers', 'styleTagAttributesResolvers'] as $property) {
                    if (property_exists($this, $property)) {
                        $this->{$property} = array_values(array_filter(
                            $this->{$property},
                            fn ($resolver): bool => ! $isTracking($resolver),
                        ));
                    }
                }
            }, $vite, Vite::class)();
        } catch (Throwable) {
            // Laravel changed its internals: tests/Feature/Admin/AdminPanelTest guards the public HTML.
        }
    }

    /**
     * Filament registers its bundled Inter font for every panel page (@filamentStyles), whatever font
     * the panel uses. The admin uses the Design 2 faces only, so the Inter stylesheet is dropped while
     * the panel is being served (there is no public API to unregister a font asset).
     */
    public static function withoutBundledInter(): void
    {
        $manager = app(AssetManager::class);
        if (! property_exists($manager, 'fonts')) {
            return; // Filament changed its internals: the Inter stylesheet stays (unused: --font-family is D2 Sans).
        }

        try {
            Closure::bind(function (): void {
                foreach ($this->fonts as $package => $fonts) {
                    $this->fonts[$package] = array_values(array_filter(
                        (array) $fonts,
                        fn ($font): bool => ! (is_object($font) && method_exists($font, 'getId') && $font->getId() === 'inter'),
                    ));
                }
            }, $manager, AssetManager::class)();
        } catch (Throwable) {
            // as above: never break the panel over an unused stylesheet
        }
    }

    /**
     * "English / العربية / Русский" in the user menu: saves the admin's interface language and reloads
     * the page (Arabic turns the panel right to left).
     *
     * @return list<Action>
     */
    private function languageActions(): array
    {
        $actions = [];
        foreach (User::ADMIN_LOCALES as $locale) {
            $actions[] = Action::make("admin-locale-{$locale}")
                ->label(AdminLocale::nativeName($locale))
                ->icon(fn (): Heroicon => app()->getLocale() === $locale ? Heroicon::OutlinedCheck : Heroicon::OutlinedLanguage)
                ->extraAttributes(['lang' => $locale, 'hreflang' => $locale])
                ->disabled(fn (): bool => app()->getLocale() === $locale)
                ->action(function () use ($locale) {
                    $user = auth()->user();
                    if ($user instanceof User) {
                        $user->forceFill(['admin_locale' => $locale])->save();
                    }
                    session()->put(AdminLocale::SESSION_KEY, $locale);

                    return redirect()->to(Livewire::originalUrl() ?: filament()->getUrl());
                });
        }

        return $actions;
    }
}
