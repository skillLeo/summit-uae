<?php

namespace App\Providers;

use App\Content\Cms\ContentCache;
use App\Content\ContentRepository;
use App\Models\Content\CalculatorRuleSet;
use App\Models\Content\CaseStudy;
use App\Models\Content\DocumentSample;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use App\Models\Content\NavigationItem;
use App\Models\Content\NavigationMenu;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Content\Situation;
use App\Models\Content\SpecialistSite;
use App\Models\Content\TeamMember;
use App\Models\Content\UiString;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One content repository per request (it memoises the cached snapshot it read).
        $this->app->scoped(ContentRepository::class);

        // spatie/laravel-translation-loader builds its loader with the app's lang path only, which drops Laravel's own
        // framework lines (validation.*, auth.*, pagination.*, passwords.*): forms then showed "validation.required".
        // Same loader class, with the framework path first and the app's lang path after it, as Laravel's FileLoader.
        $this->app->extend('translation.loader', function ($loader, $app) {
            $class = config('translation-loader.translation_manager');

            return new $class($app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin passwords (brief §12.2): at least 12 characters with letters and digits (Filament's profile and set-up).
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers());

        // The super admin may do everything (docs/briefs/design-2.1-and-admin-cms.md §12.1; App\Access\Screens).
        \Illuminate\Support\Facades\Gate::before(fn ($user, string $ability) => $user instanceof \App\Models\User && $user->canUsePanel() && $user->hasRole(\App\Access\Screens::SUPER_ADMIN) ? true : null);

        // Stable names for the polymorphic revisions (CMS, brief §8.5); other morphs keep their class names.
        Relation::morphMap([
            'page' => Page::class,
            'practice_area' => Service::class,
            'guide' => Guide::class,
            'guide_category' => GuideCategory::class,
            'case_study' => CaseStudy::class,
            'faq_group' => FaqCategory::class,
            'faq' => Faq::class,
            'team_member' => TeamMember::class,
            'associated_site' => SpecialistSite::class,
            'situation' => Situation::class,
            'document_sample' => DocumentSample::class,
            'navigation_menu' => NavigationMenu::class,
            'navigation_item' => NavigationItem::class,
            'ui_string' => UiString::class,
            'calculator_rule_set' => CalculatorRuleSet::class,
        ]);

        // A saved settings group (the settings screens, Phase 7) changes the published content cache.
        Event::listen(SettingsSaved::class, fn () => ContentCache::bump());
    }
}
