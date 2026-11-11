<?php

namespace Tests\Feature\Cms\Admin;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Editing\Preview;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\RichText;
use App\Content\Cms\Editing\TranslationStatus;
use App\Content\Cms\Status;
use App\Content\ContentRepository;
use App\Filament\Cms\Pages\ApprovalReport;
use App\Filament\Cms\Pages\Trash;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\Guides\Pages\CreateGuide;
use App\Filament\Resources\Guides\Pages\EditGuide;
use App\Filament\Resources\Guides\Pages\ListGuides;
use App\Jobs\PublishScheduledContent;
use App\Models\Content\Guide;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 editing flow (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.5, §8.7, §14.2 "Content" and "Translation"):
 * slugs and redirects, signed previews, publish and the cache, scheduled publishing, revisions, the Trash and
 * permanent delete, the missing-translations filter, Arabic fields, XSS, the block builder and "Edit this page".
 */
final class EditingTest extends TestCase
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

    private function d2(string $path): string
    {
        app()->forgetScopedInstances();

        return (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get($path)->assertOk()->getContent();
    }

    // ------------------------------------------------------------------ slugs and redirects

    public function test_a_slug_change_creates_a_301_redirect_in_every_language(): void
    {
        $this->admin();
        $service = Service::query()->findOrFail('S06');
        $old = $service->slug;
        Publisher::saveDraft($service, ['slug' => 'employment-law-uae']);
        Publisher::publish($service->refresh());

        foreach (['en', 'ar', 'ru'] as $locale) {
            $redirect = Redirect::query()->where('from_path', "/{$locale}/practice-areas/{$old}/")->first();
            $this->assertNotNull($redirect, "{$locale}: a redirect from the old address");
            $this->assertSame("/{$locale}/practice-areas/employment-law-uae/", $redirect->to_path);
            $this->assertSame(301, $redirect->code);
        }
        $this->get("/ar/practice-areas/{$old}/?x=1")->assertStatus(301);
        $this->assertStringEndsWith('/ar/practice-areas/employment-law-uae/?x=1', (string) $this->get("/ar/practice-areas/{$old}/?x=1")->headers->get('Location'));
        $this->assertSame(2, Redirect::query()->where('from_path', "/ar/practice-areas/{$old}/")->value('hits'));

        // changing it back removes the redirect from the address that is live again, and leaves no chain
        Publisher::saveDraft($service->refresh(), ['slug' => $old]);
        Publisher::publish($service->refresh());
        $this->assertNull(Redirect::query()->where('from_path', "/en/practice-areas/{$old}/")->first());
        $this->assertSame("/en/practice-areas/{$old}/", Redirect::query()->where('from_path', '/en/practice-areas/employment-law-uae/')->value('to_path'));
    }

    // ------------------------------------------------------------------ previews

    public function test_preview_urls_are_signed_and_expire(): void
    {
        $this->admin();
        $page = Page::query()->findOrFail('P01');
        $working = Publisher::workingCopy($page);
        $working['blocks'][0]['data']['hero']['lead']['ar'] = 'نص المعاينة الموقّعة';
        Publisher::saveDraft($page, $working);

        $url = Preview::url($page->refresh(), 'ar');
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        auth()->logout();

        $response = $this->get($path)->assertOk();
        $this->assertStringContainsString('نص المعاينة الموقّعة', (string) $response->getContent(), 'the preview renders the draft');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringNotContainsString('نص المعاينة الموقّعة', $this->d2('/ar/'), 'the public page keeps the published text');

        $this->get(str_replace('cms_signature=', 'cms_signature=0', $path))->assertForbidden();
        $this->get(str_replace('page%3AP01', 'page%3AP02', $path))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($path)->assertForbidden();
    }

    // ------------------------------------------------------------------ publish and the cache

    public function test_publishing_clears_the_cache_and_saving_a_draft_does_not_change_the_site(): void
    {
        $this->admin();
        $this->assertStringContainsString('Clear Legal Guidance', $this->d2('/en/'));
        $page = Page::query()->findOrFail('P01');
        $working = Publisher::workingCopy($page);
        $working['h1']['en'] = 'A Published Heading';
        Publisher::saveDraft($page, $working);
        $this->assertStringNotContainsString('A Published Heading', $this->d2('/en/'), 'a draft is not public');

        $version = ContentCache::version();
        Publisher::publish($page->refresh(), 'test');
        $this->assertNotSame($version, ContentCache::version(), 'publishing replaces the cache version');
        $this->assertStringContainsString('A Published Heading', $this->d2('/en/'));
    }

    public function test_scheduled_publishing_runs(): void
    {
        $this->admin();
        $guide = Guide::query()->findOrFail('G02');
        $working = Publisher::workingCopy($guide);
        $working['card_title']['en'] = 'Scheduled card title';
        Publisher::saveDraft($guide, $working);
        Publisher::schedule($guide->refresh(), now()->addMinutes(30));

        (new PublishScheduledContent)->handle();
        $this->assertNull(Publisher::live($guide->refresh())['card_title']['en'] === 'Scheduled card title' ? 'early' : null, 'not before its time');

        $this->travel(31)->minutes();
        (new PublishScheduledContent)->handle();
        $guide->refresh();
        $this->assertSame('Scheduled card title', Publisher::live($guide)['card_title']['en']);
        $this->assertNull($guide->publish_at);
        $this->assertNull($guide->getAttributes()['draft']);
        $this->assertSame('scheduled', $guide->revisions()->first()->note);
    }

    // ------------------------------------------------------------------ revisions

    public function test_restoring_a_revision_creates_a_draft_and_publishing_it_brings_the_version_back(): void
    {
        $this->admin();
        $guide = Guide::query()->findOrFail('G03');
        $original = Publisher::live($guide)['card_title']['en'];
        $first = Publisher::publish($guide, 'first');

        $working = Publisher::workingCopy($guide->refresh());
        $working['card_title']['en'] = 'Changed card title';
        Publisher::saveDraft($guide, $working);
        Publisher::publish($guide->refresh(), 'second');
        $this->assertSame('Changed card title', Publisher::live($guide->refresh())['card_title']['en']);

        Publisher::restore($guide, $first);
        $guide->refresh();
        $this->assertSame('Changed card title', Publisher::live($guide)['card_title']['en'], 'restoring never publishes directly');
        $this->assertSame($original, Publisher::workingCopy($guide)['card_title']['en'], 'the revision is the draft');

        Publisher::publish($guide);
        $this->assertSame($original, Publisher::live($guide->refresh())['card_title']['en']);
    }

    // ------------------------------------------------------------------ the Trash

    public function test_soft_delete_and_restore(): void
    {
        $this->admin();
        $guide = Guide::query()->findOrFail('G05');
        $anchor = (string) $guide->anchor;
        $this->assertStringContainsString('id="'.$anchor.'"', $this->d2('/en/resources/'));

        $guide->delete();
        $this->assertSoftDeleted('guides', ['code' => 'G05']);
        $this->assertStringNotContainsString('id="'.$anchor.'"', $this->d2('/en/resources/'), 'a guide in the Trash is not on the site');
        Livewire::test(Trash::class)->filterTable('type', 'guide')->assertCanSeeTableRecords([$guide]);

        Livewire::test(Trash::class)->filterTable('type', 'guide')->callTableAction('restore', $guide);
        $this->assertNotSoftDeleted('guides', ['code' => 'G05']);
        $this->assertStringContainsString('id="'.$anchor.'"', $this->d2('/en/resources/'));
    }

    public function test_permanent_delete_is_restricted_to_the_super_admin(): void
    {
        $this->admin();
        $guide = Guide::query()->findOrFail('G06');
        $guide->delete();

        config(['cms.super_admin_is_admin' => false]);
        Livewire::test(Trash::class)->filterTable('type', 'guide')->assertTableActionHidden('forceDelete', $guide);
        try {
            Trash::forceDelete($guide);
            $this->fail('a non super admin deleted permanently');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSoftDeleted('guides', ['code' => 'G06']);

        config(['cms.super_admin_is_admin' => true]);
        Livewire::test(Trash::class)->filterTable('type', 'guide')->callTableAction('forceDelete', $guide);
        $this->assertDatabaseMissing('guides', ['code' => 'G06']);
        $this->assertDatabaseMissing('revisions', ['revisionable_type' => 'guide', 'revisionable_id' => 'G06']);
    }

    // ------------------------------------------------------------------ guides: create, publish, delete (the screens)

    public function test_a_guide_is_created_as_a_pending_draft_published_and_moved_to_the_trash(): void
    {
        $this->admin();
        Livewire::test(CreateGuide::class)
            ->set('data.anchor', 'test-guide')
            ->set('data.position', 10)
            ->set('data.category', 'gc-01')
            ->set('data.title.en', 'A Test Guide')
            ->set('data.card_title.en', 'A test guide card')
            ->call('create')
            ->assertHasNoErrors();
        $guide = Guide::query()->findOrFail('G10');
        $this->assertSame(Status::DRAFT, $guide->status);
        $this->assertSame('pending', $guide->copy_source);
        $this->assertStringNotContainsString('A test guide card', $this->d2('/en/resources/'));

        Livewire::test(EditGuide::class, ['record' => 'G10'])
            ->callAction(TestAction::make('cmsPublish')->schemaComponent(), ['note' => null])
            ->assertHasNoActionErrors();
        $this->assertStringContainsString('id="test-guide"', $this->d2('/en/resources/'));

        Livewire::test(EditGuide::class, ['record' => 'G10'])->callAction('delete');
        $this->assertSoftDeleted('guides', ['code' => 'G10']);
        $this->assertStringNotContainsString('id="test-guide"', $this->d2('/en/resources/'));
    }

    // ------------------------------------------------------------------ the block builder

    public function test_an_unchanged_page_or_guide_form_saves_no_draft(): void
    {
        $this->admin();
        foreach ([[EditContentPage::class, Page::class, 'P01'], [EditContentPage::class, Page::class, 'P05'], [EditGuide::class, Guide::class, 'G01']] as [$page, $model, $key]) {
            Livewire::test($page, ['record' => $key])->call('save')->assertHasNoErrors();
            $this->assertNull($model::query()->find($key)->getAttributes()['draft'], "{$key}: no draft from an unchanged form");
        }
    }

    public function test_the_home_blocks_can_be_reordered_and_hidden_in_the_builder(): void
    {
        $this->admin();
        $component = Livewire::test(EditContentPage::class, ['record' => 'P01']);
        $items = $component->get('data.blocks');
        $keys = array_keys($items);
        // move "Who We Help" (5th) before "Services" (3rd) and switch the FAQ block off
        $who = $keys[4];
        $order = [$keys[0], $keys[1], $who, ...array_values(array_diff(array_slice($keys, 2), [$who]))];
        $reordered = [];
        foreach ($order as $key) {
            $reordered[$key] = $items[$key];
        }
        foreach ($reordered as $key => $item) {
            if ($item['data']['id'] === 'P01.faq') {
                $reordered[$key]['data']['enabled'] = false;
            }
        }
        $component->set('data.blocks', $reordered)->call('save')->assertHasNoErrors()
            ->callAction(TestAction::make('cmsPublish')->schemaComponent(), ['note' => 'order'])->assertHasNoActionErrors();

        $html = $this->d2('/en/');
        $this->assertLessThan(strpos($html, 'id="services"'), strpos($html, 'id="who-we-help"'), 'Who We Help moved before Services');
        $this->assertStringNotContainsString('id="questions"', $html, 'the FAQ block is hidden');
        $this->assertSame(['P01.hero', 'P01.start', 'P01.who'], array_slice(array_column(Publisher::live(Page::query()->find('P01'))['blocks'], 'id'), 0, 3));
    }

    public function test_rich_text_is_sanitised_on_save_and_on_render(): void
    {
        $payloads = [
            '<p>ok</p><script>alert(1)</script>',
            '<p onclick="alert(1)">ok</p>',
            '<a href="javascript:alert(1)">x</a>',
            '<img src=x onerror="alert(1)">',
            '<iframe src="https://example.com"></iframe>',
            '<p style="color:red">ok</p>',
            '<svg onload="alert(1)"></svg>',
            '<a href="https://example.com" target="_blank">x</a>',
        ];
        foreach ($payloads as $payload) {
            $clean = RichText::clean($payload);
            $this->assertDoesNotMatchRegularExpression('/<script|onerror|onclick|onload|javascript:|<iframe|<img|<svg|style=/i', $clean, $payload);
        }
        $this->assertSame('<a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a>', RichText::clean($payloads[7]));
        $this->assertSame('<h2>A</h2><ul><li><strong>b</strong></li></ul><blockquote>c</blockquote>', RichText::clean('<h2>A</h2><ul><li><strong>b</strong></li></ul><blockquote>c</blockquote>'));

        // a rich-text block added to the home: cleaned when saved, and again when rendered
        $this->admin();
        $component = Livewire::test(EditContentPage::class, ['record' => 'P01']);
        $items = $component->get('data.blocks');
        $items['new-rich'] = ['type' => 'lib-rich_text', 'data' => [
            'id' => null, 'enabled' => true, 'anchor' => 'note', 'background' => 'band', 'copy_source' => 'pending',
            'content' => ['heading' => ['en' => 'A note', 'ar' => null, 'ru' => null], 'body' => ['en' => '<p>Safe text</p><script>alert(1)</script><p onclick="x()">two</p>', 'ar' => null, 'ru' => null]],
        ]];
        $component->set('data.blocks', $items)->call('save')->assertHasNoErrors();
        $page = Page::query()->find('P01');
        $stored = json_encode(Publisher::workingCopy($page)['blocks']);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        Publisher::publish($page);

        // render: even a payload written straight into the column is cleaned
        $blocks = Publisher::live($page->refresh())['blocks'];
        foreach ($blocks as $i => $block) {
            if ($block['type'] === 'rich_text') {
                $blocks[$i]['data']['body']['en'] = '<p>Safe text</p><img src=x onerror=alert(1)>';
            }
        }
        $page->setRawAttributes(['blocks' => json_encode($blocks)] + $page->getAttributes())->save();
        $html = $this->d2('/en/');
        $this->assertStringContainsString('id="note"', $html);
        $this->assertStringContainsString('Safe text', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    // ------------------------------------------------------------------ translations

    public function test_the_missing_translations_filter_is_correct(): void
    {
        $this->admin();
        $guide = Guide::query()->findOrFail('G04');
        $working = Publisher::workingCopy($guide);
        unset($working['card_summary']['ru']);
        Publisher::saveDraft($guide, $working);
        CmsTable::forgetMissing();

        foreach (['ar', 'ru', 'any'] as $locale) {
            $expected = Guide::query()->get()->filter(fn (Guide $g) => $locale === 'any'
                ? TranslationStatus::missingLocales($g) !== []
                : in_array($locale, TranslationStatus::missingLocales($g), true))->values();
            CmsTable::forgetMissing();
            Livewire::test(ListGuides::class)->filterTable('cms_missing', $locale)
                ->assertCanSeeTableRecords($expected)
                ->assertCanNotSeeTableRecords(Guide::query()->whereNotIn('code', $expected->pluck('code'))->get());
        }
        $this->assertContains('ru', TranslationStatus::missingLocales($guide->refresh()));
        $this->assertSame(['card_summary'], TranslationStatus::missing($guide)['ru']);
    }

    public function test_arabic_fields_render_right_to_left(): void
    {
        $this->admin();
        $html = Livewire::test(EditGuide::class, ['record' => 'G01'])->call('setEditingLocale', 'ar')->html();
        $this->assertMatchesRegularExpression('/<input[^>]*lang="ar"[^>]*dir="rtl"|<input[^>]*dir="rtl"[^>]*lang="ar"/', $html, 'the Arabic inputs are rtl');
        $this->assertMatchesRegularExpression('/class="[^"]*cms-locale cms-locale-ar(?! cms-locale--off)[^"]*"[^>]*dir="rtl"/', $html, 'the Arabic field is shown and rtl');
        $this->assertMatchesRegularExpression('/class="[^"]*cms-locale cms-locale-en cms-locale--off/', $html, 'English is hidden while editing Arabic');
        $this->assertStringContainsString('Editing:', $html);
    }

    // ------------------------------------------------------------------ approval and "Edit this page"

    public function test_the_approval_report_lists_pending_items_and_exports_them(): void
    {
        $this->admin();
        Livewire::test(ApprovalReport::class)->assertOk()->assertSee('P01.start');
        $response = Livewire::test(ApprovalReport::class)->call('export');
        $response->assertFileDownloaded();
    }

    public function test_the_edit_bar_is_shown_to_admins_only_and_never_cached(): void
    {
        $guest = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get('/en/');
        $this->assertStringNotContainsString('data-cms-editbar', (string) $guest->getContent());
        $this->assertStringNotContainsString('no-store', (string) $guest->headers->get('Cache-Control'));

        $this->admin();
        app()->forgetScopedInstances();
        $admin = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get('/en/practice-areas/labor-law/');
        $this->assertStringContainsString('data-cms-editbar', (string) $admin->getContent());
        $this->assertStringContainsString('/admin/practice-areas/S06/edit', (string) $admin->getContent());
        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
    }
}
