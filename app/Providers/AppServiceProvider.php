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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
