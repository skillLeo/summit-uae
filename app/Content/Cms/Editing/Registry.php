<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\Schema;
use App\Models\Content\CalculatorRuleSet;
use App\Models\Content\CaseStudy;
use App\Models\Content\ContentModel;
use App\Models\Content\DocumentSample;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use App\Models\Content\NavigationMenu;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Content\Situation;
use App\Models\Content\SpecialistSite;
use App\Models\Content\TeamMember;
use App\Models\Content\UiString;
use Illuminate\Database\Eloquent\Model;

/**
 * What the admin edits on each CMS model (docs/briefs/design-2.1-and-admin-cms.md §8.5, §8.11): the editable columns and
 * how each one is stored. Drafts, revisions, previews, the translation status and the field diff all work on these
 * columns only, so one record's "working copy" is always the same set of fields.
 *
 * Kinds of column:
 *   plain         a scalar (slug, position, anchor, booleans, dates)
 *   json          a language-independent structure (a list of codes)
 *   translatable  {en, ar, ru}; each language's value may be a text or a structure (spatie/laravel-translatable)
 *   tree          one structure shared by every language with {en, ar, ru} leaves (page blocks, document parts)
 *   items         NavigationMenu only: the menu's items (navigation_items rows), edited and published with the menu
 */
final class Registry
{
    public const PLAIN = 'plain';

    public const JSON = 'json';

    public const TRANSLATABLE = 'translatable';

    public const TREE = 'tree';

    public const ITEMS = 'items';

    /** Columns that are traceability, not copy: never checked for missing translations. */
    public const NOT_TRANSLATED = ['registry_label'];

    /** @var array<class-string, array<string, string>> */
    private static array $cache = [];

    /**
     * The editable columns of a model and their kind, in form order.
     *
     * @param  Model|class-string  $model
     * @return array<string, string>
     */
    public static function fields(Model|string $model): array
    {
        $class = is_string($model) ? $model : $model::class;

        return self::$cache[$class] ??= self::build($class);
    }

    /** @return array<string, string> */
    private static function build(string $class): array
    {
        if (is_subclass_of($class, ContentModel::class)) {
            $schema = Schema::DATASETS[$class::DATASET];
            $fields = [];
            foreach ($schema['plain'] as $column) {
                $fields[$column] = self::PLAIN;
            }
            foreach ($schema['json'] as $column) {
                $fields[$column] = self::JSON;
            }
            foreach ($schema['translatable'] as $column) {
                $fields[$column] = self::TRANSLATABLE;
            }
            if ($schema['blocks'] !== null) {
                $fields['blocks'] = self::TREE;
            }
            foreach ($schema['extra'] as $column) {
                if ($column === 'key') {
                    continue; // the page key is structure (home, services …), read-only
                }
                $fields[$column] = match ($column) {
                    'seo_title', 'meta_description' => self::TRANSLATABLE,
                    'focal_point', 'placements' => self::JSON,
                    default => self::PLAIN,
                };
            }

            return $fields;
        }

        return match ($class) {
            Situation::class => [
                'practice_area' => self::PLAIN, 'position' => self::PLAIN, 'title' => self::TRANSLATABLE,
                'documents' => self::TRANSLATABLE, 'first_steps' => self::TRANSLATABLE, 'mistakes' => self::TRANSLATABLE,
                'featured' => self::PLAIN, 'featured_position' => self::PLAIN,
            ],
            DocumentSample::class => [
                'position' => self::PLAIN, 'enabled' => self::PLAIN, 'practice_area' => self::PLAIN,
                'title' => self::TRANSLATABLE, 'parts' => self::TREE,
            ],
            NavigationMenu::class => ['name' => self::PLAIN, 'description' => self::PLAIN, 'items' => self::ITEMS],
            UiString::class => ['text' => self::TRANSLATABLE, 'note' => self::PLAIN],
            CalculatorRuleSet::class => [
                'calculator' => self::PLAIN, 'version' => self::PLAIN, 'effective_from' => self::PLAIN,
                'rules' => self::JSON, 'legal_basis' => self::TRANSLATABLE,
            ],
            default => [],
        };
    }

    /** Whether the model takes part in drafts, revisions and the Trash. */
    public static function supports(Model|string $model): bool
    {
        return self::fields($model) !== [];
    }

    /**
     * Every CMS model the admin edits, by its morph name (the revisions' revisionable_type).
     *
     * @return array<string, class-string<Model>>
     */
    public static function models(): array
    {
        return [
            'page' => Page::class,
            'practice_area' => Service::class,
            'guide' => Guide::class,
            'guide_category' => GuideCategory::class,
            'case_study' => CaseStudy::class,
            'faq' => Faq::class,
            'faq_group' => FaqCategory::class,
            'team_member' => TeamMember::class,
            'situation' => Situation::class,
            'document_sample' => DocumentSample::class,
            'associated_site' => SpecialistSite::class,
            'navigation_menu' => NavigationMenu::class,
            'ui_string' => UiString::class,
            'calculator_rule_set' => CalculatorRuleSet::class,
        ];
    }

    /** The morph name of a model class (page, guide …). */
    public static function type(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        return (string) (array_search($class, self::models(), true) ?: class_basename($class));
    }

    /** A short human label of a record (its title in the language, else its code). */
    public static function label(Model $record, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $column = match ($record::class) {
            Page::class => 'h1',
            Service::class, Guide::class, CaseStudy::class, Situation::class, DocumentSample::class => 'title',
            GuideCategory::class, FaqCategory::class, TeamMember::class => 'name',
            Faq::class => 'question',
            SpecialistSite::class => 'label',
            default => null,
        };
        if ($column !== null) {
            $value = Publisher::live($record)[$column] ?? null;
            if (is_array($value)) {
                $text = $value[$locale] ?? $value['en'] ?? reset($value);
                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        return match ($record::class) {
            NavigationMenu::class => (string) $record->name,
            UiString::class => $record->group.'.'.$record->key,
            CalculatorRuleSet::class => $record->calculator.' v'.$record->version,
            default => (string) $record->getKey(),
        };
    }
}
