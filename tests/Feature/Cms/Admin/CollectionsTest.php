<?php

namespace Tests\Feature\Cms\Admin;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\TranslationStatus;
use App\Content\Cms\Status;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\AssociatedSites\AssociatedSiteResource;
use App\Filament\Resources\AssociatedSites\Pages\EditAssociatedSite;
use App\Filament\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Resources\CaseStudies\Pages\CreateCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Filament\Resources\FaqGroups\FaqGroupResource;
use App\Filament\Resources\FaqGroups\Pages\EditFaqGroup;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\PracticeAreas\Pages\EditPracticeArea;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Filament\Resources\Situations\Pages\CreateSituation;
use App\Filament\Resources\Situations\Pages\EditSituation;
use App\Filament\Resources\Situations\SituationResource;
use App\Models\Content\CaseStudy;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Service;
use App\Models\Content\Situation;
use App\Models\Content\SpecialistSite;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 4 collection screens (docs/briefs/design-2.1-and-admin-cms.md §8.2, §9): Practice Areas, Case Studies, FAQs,
 * FAQ groups, Situations and Associated Sites. Every list and edit screen opens for an admin and is refused to anyone
 * else; saving stores a draft (the site keeps the published row), publishing applies it; a new record is a pending
 * draft with the next key; an unchanged form saved again stores nothing (the form round-trips the stored structure).
 */
final class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    /** @return array<string, array{0: class-string, 1: class-string<Model>, 2: string}> */
    public static function screens(): array
    {
        return [
            'practice areas' => [PracticeAreaResource::class, Service::class, 'S05'],
            'case studies' => [CaseStudyResource::class, CaseStudy::class, 'case-01'],
            'faqs' => [FaqResource::class, Faq::class, 'faq-01'],
            'faq groups' => [FaqGroupResource::class, FaqCategory::class, 'cat-01'],
            'situations' => [SituationResource::class, Situation::class, 'S05-2'],
            'associated sites' => [AssociatedSiteResource::class, SpecialistSite::class, 'X01'],
        ];
    }

    #[DataProvider('screens')]
    public function test_the_list_and_edit_screens_open_for_an_admin_only(string $resource, string $model, string $key): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get($resource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get($resource::getUrl('edit', ['record' => $key]))->assertForbidden();

        $this->admin();
        $this->get($resource::getUrl('index'))->assertOk()->assertSee($key);
        $this->get($resource::getUrl('edit', ['record' => $key]))->assertOk();
        $this->get($resource::getUrl('edit', ['record' => $key]).'?locale=ar')->assertOk();
    }

    /** @return array<string, array{0: class-string, 1: class-string<Model>, 2: string}> */
    public static function editPages(): array
    {
        return [
            'practice area' => [EditPracticeArea::class, Service::class, 'S05'],
            'case study' => [EditCaseStudy::class, CaseStudy::class, 'case-03'],
            'faq' => [EditFaq::class, Faq::class, 'faq-12'],
            'faq group' => [EditFaqGroup::class, FaqCategory::class, 'cat-04'],
            'situation' => [EditSituation::class, Situation::class, 'S05-3'],
            'associated site' => [EditAssociatedSite::class, SpecialistSite::class, 'X03'],
        ];
    }

    /**
     * An unchanged form saved twice stores no draft: every field gives back exactly the stored value.
     */
    #[DataProvider('editPages')]
    public function test_an_unchanged_form_saves_no_draft(string $page, string $model, string $key): void
    {
        $this->admin();
        $before = $model::query()->findOrFail($key)->getAttributes();

        foreach ([1, 2] as $round) {
            Livewire::test($page, ['record' => $key])->call('save')->assertHasNoErrors();
            $record = $model::query()->findOrFail($key);
            $this->assertNull($record->getAttributes()['draft'], "round {$round}: no draft; differs in ".json_encode($this->differences($record)));
        }
        $after = $model::query()->findOrFail($key)->getAttributes();
        foreach (array_keys(Publisher::live($record)) as $column) {
            $this->assertSame($before[$column] ?? null, $after[$column] ?? null, "{$column} is byte-identical");
        }
    }

    /** @return array<string, array{0: class-string, 1: class-string<Model>, 2: string, 3: string}> */
    public static function drafts(): array
    {
        return [
            'practice area' => [EditPracticeArea::class, Service::class, 'S05', 'title'],
            'case study' => [EditCaseStudy::class, CaseStudy::class, 'case-03', 'home_title'],
            'faq' => [EditFaq::class, Faq::class, 'faq-12', 'question'],
            'faq group' => [EditFaqGroup::class, FaqCategory::class, 'cat-04', 'name'],
            'situation' => [EditSituation::class, Situation::class, 'S05-3', 'title'],
            'associated site' => [EditAssociatedSite::class, SpecialistSite::class, 'X03', 'label'],
        ];
    }

    /** Save keeps the published row and stores the change as a draft; Publish (the Publishing panel) applies it. */
    #[DataProvider('drafts')]
    public function test_saving_stores_a_draft_and_publishing_applies_it(string $page, string $model, string $key, string $field): void
    {
        $this->admin();
        $record = $model::query()->findOrFail($key);
        $liveBefore = $record->getAttributes()[$field];
        $text = 'نص تجريبي للمسودة';

        $component = Livewire::test($page, ['record' => $key])
            ->set("data.{$field}.ar", $text)
            ->call('save')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame($liveBefore, $record->getAttributes()[$field], 'the site keeps the published value');
        $this->assertSame(Status::PUBLISHED, $record->status);
        $this->assertSame($text, Publisher::draft($record)[$field]['ar'] ?? null, 'the draft holds the change');

        $component->callAction(TestAction::make('cmsPublish')->schemaComponent(), ['note' => 'admin test'])
            ->assertHasNoActionErrors();

        $record->refresh();
        $this->assertNull($record->getAttributes()['draft'], 'publishing clears the draft');
        $this->assertSame($text, Publisher::live($record)[$field]['ar'] ?? null, 'the site shows the change');
        $this->assertSame(Status::PUBLISHED, $record->status);
        $this->assertSame('admin test', $record->revisions()->first()?->note);
    }

    public function test_a_new_case_study_is_a_pending_draft_with_the_next_key(): void
    {
        $this->admin();
        Livewire::test(CreateCaseStudy::class)
            ->set('data.title.en', 'A new case study')
            ->set('data.anchor', 'case-new')
            ->call('create')
            ->assertHasNoErrors();

        $record = CaseStudy::query()->findOrFail('case-07');
        $this->assertSame(Status::DRAFT, $record->status);
        $this->assertSame(CopySource::PENDING, $record->copy_source);
        $this->assertSame('A new case study', Publisher::live($record)['title']['en']);
        $this->assertSame('case-new', $record->anchor);
        $this->assertNull(content()->record('case_studies', 'case-07'), 'not on the site before it is published');
    }

    public function test_a_new_faq_is_a_pending_draft_with_the_next_key(): void
    {
        $this->admin();
        Livewire::test(CreateFaq::class)
            ->set('data.question.en', 'A new question?')
            ->set('data.category', 'cat-02')
            ->call('create')
            ->assertHasNoErrors();

        $record = Faq::query()->findOrFail('faq-75');
        $this->assertSame(Status::DRAFT, $record->status);
        $this->assertSame(CopySource::PENDING, $record->copy_source);
        $this->assertSame('cat-02', $record->category);
        $this->assertNull(Publisher::live($record)['placements'], 'no placements is stored as null');
    }

    public function test_a_new_situation_gets_the_next_number_of_its_practice_area(): void
    {
        $this->admin();
        Livewire::test(CreateSituation::class)
            ->set('data.practice_area', 'S05')
            ->set('data.title.en', 'A new situation.')
            ->call('create')
            ->assertHasNoErrors();

        $record = Situation::query()->findOrFail('S05-5');
        $this->assertSame('S05', $record->practice_area);
        $this->assertSame(5, $record->position);
        $this->assertSame(CopySource::PENDING, $record->copy_source);
        $this->assertNull(Publisher::live($record)['documents'], 'empty lists inherit the practice area\'s');
    }

    public function test_the_missing_translations_filter_lists_what_translation_status_reports(): void
    {
        $this->admin();
        $faq = Faq::query()->findOrFail('faq-20');
        $question = Publisher::live($faq)['question'];
        unset($question['ar']);
        Publisher::saveDraft($faq, ['question' => $question]);
        CmsTable::forgetMissing();

        $all = Faq::query()->get();
        foreach (['ar', 'any', 'ru'] as $locale) {
            $expected = $all->filter(fn (Faq $f) => $locale === 'any' ? TranslationStatus::missingLocales($f) !== [] : in_array($locale, TranslationStatus::missingLocales($f), true));
            $component = Livewire::test(ListFaqs::class)->filterTable('cms_missing', $locale);
            $component->assertCanSeeTableRecords($expected)->assertCanNotSeeTableRecords($all->diff($expected));
            $component->assertCountTableRecords($expected->count());
            if ($locale !== 'ru') {
                $this->assertContains('faq-20', $expected->pluck('code')->all());
            }
        }
    }

    public function test_the_faq_list_filters_by_group_and_searches_the_question(): void
    {
        $this->admin();
        $group = Faq::query()->where('category', 'cat-03')->get();
        Livewire::test(ListFaqs::class)
            ->filterTable('category', 'cat-03')
            ->assertCanSeeTableRecords($group)
            ->assertCountTableRecords($group->count());

        $faq = Faq::query()->findOrFail('faq-12');
        $words = mb_substr((string) Publisher::live($faq)['question']['ar'], 4, 12);
        Livewire::test(ListFaqs::class)->removeTableFilters()->searchTable($words)->assertCanSeeTableRecords([$faq])->assertCountTableRecords(1);
    }

    public function test_practice_areas_are_the_site_map_and_their_slug_is_validated(): void
    {
        $this->admin();
        $this->assertFalse(PracticeAreaResource::canCreate());
        $this->assertFalse(PracticeAreaResource::canDelete(Service::query()->findOrFail('S05')));
        $this->get('/admin/practice-areas/create')->assertNotFound();

        Livewire::test(EditPracticeArea::class, ['record' => 'S05'])->assertActionHidden('delete');
        Livewire::test(EditPracticeArea::class, ['record' => 'S05'])
            ->set('data.slug', 'Property Disputes')
            ->call('save')
            ->assertHasFormErrors(['slug']);
        Livewire::test(EditPracticeArea::class, ['record' => 'S05'])
            ->set('data.slug', 'labor-law')
            ->call('save')
            ->assertHasFormErrors(['slug']);
        $this->assertNull(Service::query()->findOrFail('S05')->getAttributes()['draft']);
    }

    public function test_the_related_guides_keep_their_order(): void
    {
        $this->admin();
        $component = Livewire::test(EditPracticeArea::class, ['record' => 'S01']);
        $state = $component->get('data.related_guides');
        $this->assertSame(['G02', 'G03'], array_column(array_values($state), 'code'));
        $keys = array_keys($state);
        $component->set('data.related_guides', [$keys[1] => ['code' => 'G03'], $keys[0] => ['code' => 'G02']])->call('save')->assertHasNoErrors();
        $this->assertSame(['G03', 'G02'], Publisher::draft(Service::query()->findOrFail('S01'))['related_guides']);
    }

    public function test_an_associated_site_needs_an_https_address(): void
    {
        $this->admin();
        Livewire::test(EditAssociatedSite::class, ['record' => 'X01'])
            ->set('data.url', 'http://laborlawuae.com')
            ->call('save')
            ->assertHasFormErrors(['url']);
    }

    /** @return list<string> the paths where the draft differs from the live value (at most 12) */
    private function differences(Model $record): array
    {
        $out = [];
        $this->walkDiff(Publisher::live($record), Publisher::draft($record) ?? [], '', $out);

        return array_slice($out, 0, 12);
    }

    /** @param list<string> $out */
    private function walkDiff(mixed $a, mixed $b, string $path, array &$out): void
    {
        if (is_array($a) && is_array($b)) {
            if (array_keys($a) !== array_keys($b)) {
                $out[] = $path.' keys '.json_encode(array_keys($a)).' vs '.json_encode(array_keys($b));
            }
            foreach ($a as $k => $v) {
                if (array_key_exists($k, $b)) {
                    $this->walkDiff($v, $b[$k], $path.'.'.$k, $out);
                }
            }

            return;
        }
        if ($a !== $b) {
            $out[] = $path.': '.json_encode($a, JSON_UNESCAPED_UNICODE).' vs '.json_encode($b, JSON_UNESCAPED_UNICODE);
        }
    }
}
