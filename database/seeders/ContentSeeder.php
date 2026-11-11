<?php

namespace Database\Seeders;

use App\Content\ContentRepository;
use App\Content\Import\ContentSchema;
use App\Models\Content\CaseStudy;
use App\Models\Content\ContentModel;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Content\Setting;
use App\Models\Content\SpecialistSite;
use App\Models\Content\TeamMember;
use App\Models\Content\UiString;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads database/content/*.json (written by `php artisan summit:import`) into the content tables.
 */
final class ContentSeeder extends Seeder
{
    /** @var array<string, class-string<ContentModel>> */
    private const MODELS = [
        'pages' => Page::class,
        'services' => Service::class,
        'guides' => Guide::class,
        'guide_categories' => GuideCategory::class,
        'case_studies' => CaseStudy::class,
        'faq_categories' => FaqCategory::class,
        'faqs' => Faq::class,
        'team_members' => TeamMember::class,
        'specialist_sites' => SpecialistSite::class,
    ];

    public function run(): void
    {
        $dir = database_path('content');
        if (! is_file($dir.'/pages.json')) {
            throw new RuntimeException('database/content is empty. Run `php artisan summit:import` first.');
        }

        DB::transaction(function () use ($dir) {
            foreach (self::MODELS as $dataset => $model) {
                $model::query()->delete();
                foreach ($this->read($dir, $dataset) as $code => $fields) {
                    $this->insert($dataset, $model, (string) $code, $fields);
                }
            }
            foreach (['ui_strings' => UiString::class, 'settings' => Setting::class] as $dataset => $model) {
                $model::query()->delete();
                foreach ($this->read($dir, $dataset) as $key => $values) {
                    $m = new $model;
                    $m->key = $key;
                    $m->setTranslations('value', array_filter($values, fn ($v) => $v !== null));
                    $m->save();
                }
            }
        });

        app(ContentRepository::class)->flush();
    }

    /** @return array<string, mixed> */
    private function read(string $dir, string $dataset): array
    {
        $file = $dir.'/'.$dataset.'.json';

        return is_file($file) ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param  class-string<ContentModel>  $model
     * @param  array<string, mixed>  $fields
     */
    private function insert(string $dataset, string $model, string $code, array $fields): void
    {
        $fields += $this->registryDefaults($dataset, $code);

        /** @var ContentModel $m */
        $m = new $model;
        $m->code = $code;
        $data = [];
        $meta = [];
        foreach ($fields as $field => $value) {
            if (in_array($field, $model::PLAIN, true)) {
                $m->{$field} = $value;
            } elseif (in_array($field, $model::columns(), true)) {
                $m->setTranslations($field, array_filter((array) $value, fn ($v) => $v !== null));
            } elseif (ContentSchema::isPlain($dataset, $field)) {
                $meta[$field] = $value;
            } else {
                foreach ((array) $value as $locale => $v) {
                    if ($v !== null) {
                        $data[$locale][$field] = $v;
                    }
                }
            }
        }
        $m->setTranslations('data', $data);
        $m->meta = $meta;
        $m->save();
    }

    /** Slugs, anchors and positions come from config/summit.php, not from the copy. */
    private function registryDefaults(string $dataset, string $code): array
    {
        return match ($dataset) {
            'pages' => [
                'slug' => config("summit.pages.{$code}.path"),
                'position' => array_search($code, array_keys(config('summit.pages')), true) ?: 0,
            ],
            'services' => [
                'slug' => config("summit.services.{$code}"),
                'position' => (int) substr($code, 1),
            ],
            'guides' => [
                'anchor' => config("summit.guides.{$code}"),
                'position' => (int) substr($code, 1),
            ],
            // case-01, cat-01, faq-01, gc-01 … carry their order in the ID; the ID is the anchor.
            'case_studies', 'faq_categories', 'faqs' => [
                'anchor' => $code,
                'position' => (int) preg_replace('/\D/', '', $code),
            ],
            'guide_categories' => ['position' => (int) preg_replace('/\D/', '', $code)],
            'team_members' => ['anchor' => $code, 'position' => 0],
            default => [],
        };
    }
}
