<?php

namespace Tests\Feature\Cms\Admin;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\TranslationStatus;
use App\Filament\Cms\Pages\Seo;
use App\Filament\Cms\Pages\Settings\GeneralContact;
use App\Filament\Cms\Pages\Settings\OfficeHours;
use App\Filament\Cms\Pages\Settings\WhatsApp;
use App\Filament\Resources\Calculators\CalculatorResource;
use App\Filament\Resources\Calculators\Pages\EditCalculatorRuleSet;
use App\Filament\Resources\Calculators\Pages\ListCalculatorRuleSets;
use App\Filament\Resources\InterfaceText\InterfaceTextCsv;
use App\Filament\Resources\InterfaceText\Pages\EditInterfaceText;
use App\Filament\Resources\InterfaceText\Pages\ListInterfaceText;
use App\Filament\Resources\Navigation\Pages\EditNavigationMenu;
use App\Filament\Resources\Navigation\Pages\ListNavigationMenus;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Content\CalculatorRuleSet;
use App\Models\Content\NavigationItem;
use App\Models\Content\NavigationMenu;
use App\Models\Content\UiString;
use App\Models\Redirect;
use App\Models\User;
use App\Settings\SeoSettings;
use App\Support\Seo as SeoMeta;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Site screens of the admin (Phase 4; docs/briefs/design-2.1-and-admin-cms.md §8.2, §9): Navigation, Interface
 * Text with its CSV round trip, Redirects, SEO, Calculators (super admin only) and the first three Settings screens
 * (General and Contact, Office Hours, WhatsApp), checked through their Filament pages and on the rendered Design 2
 * site.
 */
final class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Livewire::flushState(); // see AdminPanelTest: no Livewire assets leak into a later public page
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user);

        return $user;
    }

    private function get2(string $url): string
    {
        return (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get($url)->assertOk()->getContent();
    }

    /** @return list<string> the data-nav-key of the header row's links, in order */
    private function headerKeys(string $html): array
    {
        $this->assertSame(1, preg_match('#<nav class="container d2-nav"(.*?)</nav>#s', $html, $nav), 'the header row is rendered');
        preg_match_all('/data-nav-key="([^"]+)"/', $nav[1], $m);

        return $m[1];
    }

    // ------------------------------------------------------------------ navigation

    public function test_navigation_edit_keeps_link_keys_and_publish_reorders_the_header(): void
    {
        $undo = Repeater::fake();
        $menu = NavigationMenu::query()->where('key', 'primary')->firstOrFail();
        $keys = NavigationItem::query()->where('menu_id', $menu->id)->orderBy('position')->pluck('link_key', 'id')->all();
        $before = $this->headerKeys($this->get2('/en/'));
        $this->assertSame(['home', 'resources', 'cases', 'faq', 'about', 'contact'], $before, 'the header row (Practice Areas is the mega menu)');

        $page = Livewire::test(EditNavigationMenu::class, ['record' => $menu->getRouteKey()]);
        $items = array_values($page->get('data.items'));
        $this->assertSame(array_values($keys), array_column($items, 'link_key'), 'the form shows the site-map keys');

        // Try to change every key, forge a key on a new item, and move Contact Us (H-L007) to the front.
        foreach ($items as $i => $item) {
            $items[$i]['link_key'] = 'HACK-'.$i;
        }
        $contact = array_values(array_filter($items, fn ($item) => $keys[$item['id']] === 'H-L007'))[0];
        $items = [$contact, ...array_values(array_filter($items, fn ($item) => $item['id'] !== $contact['id']))];
        $items[] = ['id' => null, 'link_key' => 'H-L999', 'target_type' => 'url', 'target' => '/en/faq/', 'label' => ['en' => 'New link', 'ar' => null, 'ru' => null], 'new_tab' => false, 'enabled' => false];
        $page->fillForm(['items' => $items])->call('save')->assertHasNoFormErrors();

        $menu->refresh();
        $draft = Publisher::draft($menu);
        $this->assertNotNull($draft, 'saving keeps a draft');
        $this->assertSame(['H-L007', 'H-L001', 'H-L002', 'H-L003', 'H-L004', 'H-L005', 'H-L006', null], array_column($draft['items'], 'link_key'), 'the keys stay with their items; a new item has none');
        $this->assertSame($before, $this->headerKeys($this->get2('/en/')), 'the site keeps the published order until publishing');

        Publisher::publish($menu);
        $this->assertSame(['contact', 'home', 'resources', 'cases', 'faq', 'about'], $this->headerKeys($this->get2('/en/')), 'the new order on the public header (the new item is off)');
        $stored = array_intersect_key(NavigationItem::query()->where('menu_id', $menu->id)->pluck('link_key', 'id')->all(), $keys);
        ksort($stored);
        $this->assertSame($keys, $stored, 'no stored key changed');
        $this->assertNull(NavigationItem::query()->where('menu_id', $menu->id)->where('target', '/en/faq/')->value('link_key'));
        $undo();
    }

    public function test_navigation_targets_are_validated_per_type(): void
    {
        $undo = Repeater::fake();
        $menu = NavigationMenu::query()->where('key', 'footer_useful')->firstOrFail();
        $page = Livewire::test(EditNavigationMenu::class, ['record' => $menu->getRouteKey()]);
        $items = array_values($page->get('data.items'));
        $items[0]['target'] = 'P99';
        $page->fillForm(['items' => $items])->call('save')->assertHasFormErrors(['items.0.target']);

        $items[0]['target'] = 'P07';
        $items[1]['target_type'] = 'record';
        $items[1]['target'] = 'S99';
        $page->fillForm(['items' => $items])->call('save')->assertHasFormErrors(['items.1.target']);
        $undo();

        Livewire::test(ListNavigationMenus::class)->assertCanSeeTableRecords(NavigationMenu::query()->get())->assertOk();
    }

    // ------------------------------------------------------------------ interface text

    public function test_interface_text_csv_round_trip_creates_drafts_and_rejects_unknown_keys(): void
    {
        $line = UiString::query()->where('group', 'ui')->where('key', 'nav.home')->firstOrFail();
        $approved = $line->text['en'];
        $csv = InterfaceTextCsv::export(UiString::query()->where('group', 'ui')->where('key', 'like', 'nav.%'));
        $this->assertStringStartsWith("\xEF\xBB\xBF".'group,key,en,ar,ru,copy_source,note'."\n", $csv);

        // Edit the exported file like a translator: one English text changed, one unknown key, one malformed row.
        $rows = array_map(fn ($l) => str_getcsv($l, escape: ''), array_filter(explode("\n", substr($csv, 3))));
        foreach ($rows as $i => $row) {
            if ($row[0] === 'ui' && $row[1] === 'nav.home') {
                $rows[$i][2] = 'Home (CSV)';
            }
        }
        $rows[] = ['ui', 'nav.does_not_exist', 'Nope', '', '', 'docx', ''];
        $rows[] = ['ui', 'nav.home'];
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row, escape: '');
        }
        rewind($out);
        $edited = "\xEF\xBB\xBF".stream_get_contents($out);

        $file = UploadedFile::fake()->createWithContent('interface-text.csv', $edited);
        Livewire::test(ListInterfaceText::class)
            ->callAction('importCsv', data: ['file' => $file, 'publish' => false])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $line->refresh();
        $this->assertSame('Home (CSV)', Publisher::draft($line)['text']['en'], 'the change is a draft');
        $this->assertSame($approved, Publisher::live($line)['text']['en'], 'the site keeps the published text');
        $this->assertStringNotContainsString('Home (CSV)', $this->get2('/en/'));
        $this->assertSame(1, UiString::query()->whereNotNull('draft')->count(), 'only the changed row has a draft');
        $this->assertFalse(UiString::query()->where('key', 'nav.does_not_exist')->exists(), 'an unknown key is never created');

        $report = InterfaceTextCsv::import($edited, publish: true);
        $this->assertSame(0, $report['updated'], 'the file equals the waiting draft');
        $this->assertSame(1, $report['published'], 'import and publish publishes it');
        $this->assertSame(['unknown', 'malformed'], array_column($report['rejected'], 'reason'));
        $this->assertSame('Home (CSV)', Publisher::live($line->refresh())['text']['en'], 'import and publish');

        $this->assertSame('header', InterfaceTextCsv::import("en,ar\nHello,مرحبا\n")['error'], 'a file without group and key is refused');
        $this->assertSame('placeholders', InterfaceTextCsv::import("group,key,en\nproposed,d2.hours.ahead,UAE time is ahead\n")['rejected'][0]['reason'] ?? null);
    }

    public function test_the_missing_translations_filter_matches_the_translation_status(): void
    {
        foreach (['nav.home', 'nav.about'] as $key) {
            $line = UiString::query()->where('group', 'ui')->where('key', $key)->firstOrFail();
            $text = $line->text;
            unset($text['ar']);
            $line->text = $text;
            $line->save();
        }
        $expected = fn (string $locale) => UiString::query()->get()
            ->filter(fn (UiString $l) => $locale === 'any' ? TranslationStatus::missingLocales($l) !== [] : in_array($locale, TranslationStatus::missingLocales($l), true))
            ->pluck('id')->sort()->values()->all();
        $this->assertNotEmpty($expected('ar'));

        foreach (['ar', 'any', 'ru'] as $locale) {
            $list = Livewire::test(ListInterfaceText::class)->filterTable('cms_missing', $locale);
            $this->assertSame($expected($locale), $list->instance()->getFilteredTableQuery()->pluck('id')->sort()->values()->all(), "missing in {$locale}");
        }

        $list = Livewire::test(ListInterfaceText::class)->removeTableFilters()->searchTable('Home');
        $this->assertContains(UiString::query()->where('key', 'nav.home')->value('id'), $list->instance()->getFilteredTableQuery()->pluck('id')->all(), 'search finds a text');
        $list = Livewire::test(ListInterfaceText::class)->removeTableFilters()->searchTable('الرئيسية');
        $this->assertNotEmpty($list->instance()->getFilteredTableQuery()->pluck('id')->all(), 'search finds Arabic text');
    }

    public function test_an_interface_string_is_edited_as_a_draft_and_keeps_its_placeholders(): void
    {
        $line = UiString::query()->where('group', 'proposed')->where('key', 'd2.hours.ahead')->firstOrFail();
        Livewire::test(EditInterfaceText::class, ['record' => $line->getRouteKey()])
            ->fillForm(['text.en' => 'UAE time is ahead of yours'])
            ->call('save')
            ->assertHasFormErrors(['text.en']);

        $plain = UiString::query()->where('group', 'ui')->where('key', 'nav.about')->firstOrFail();
        Livewire::test(EditInterfaceText::class, ['record' => $plain->getRouteKey()])
            ->fillForm(['text.en' => 'Our story'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Our story', Publisher::draft($plain->refresh())['text']['en']);
    }

    // ------------------------------------------------------------------ redirects

    public function test_redirect_validation_forbids_chains(): void
    {
        Livewire::test(CreateRedirect::class)
            ->fillForm(['from_path' => '/en/old-page/', 'to_path' => '/en/about/', 'code' => 301, 'enabled' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertTrue(Redirect::query()->where('from_path', '/en/old-page/')->exists());

        $cases = [
            [['from_path' => '/en/about/', 'to_path' => '/en/contact/'], 'from_path'],      // from = another's to: a chain
            [['from_path' => '/en/x/', 'to_path' => '/en/old-page/'], 'to_path'],            // to = another's from: a chain
            [['from_path' => '/en/x/', 'to_path' => '/en/old-page'], 'to_path'],             // same, without the trailing slash
            [['from_path' => '/en/old-page', 'to_path' => '/en/faq/'], 'from_path'],         // taken
            [['from_path' => '/en/y/', 'to_path' => '/en/y/'], 'to_path'],                   // same
            [['from_path' => 'en/z/', 'to_path' => '/en/faq/'], 'from_path'],                // no leading slash
            [['from_path' => '/admin/pages', 'to_path' => '/en/faq/'], 'from_path'],         // reserved
            [['from_path' => '/en/w/', 'to_path' => 'javascript:alert(1)'], 'to_path'],      // not a path or URL
        ];
        foreach ($cases as [$data, $field]) {
            Livewire::test(CreateRedirect::class)->fillForm($data + ['code' => 301, 'enabled' => true])->call('create')->assertHasFormErrors([$field]);
        }
        $this->assertSame(1, Redirect::query()->count());

        $response = $this->get('/en/old-page/')->assertStatus(301);
        $this->assertStringEndsWith('/en/about/', (string) $response->headers->get('Location'));
        Livewire::test(ListRedirects::class)->assertCanSeeTableRecords(Redirect::query()->get());
    }

    // ------------------------------------------------------------------ calculators

    public function test_the_calculator_screens_are_super_admin_only(): void
    {
        $record = CalculatorRuleSet::query()->where('calculator', 'gratuity')->firstOrFail();
        $this->get(CalculatorResource::getUrl('index'))->assertOk();
        $this->get(CalculatorResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSee('cms-calc-results', escape: false);

        // the calculator rules belong to the super admin: an admin is refused
        $this->grantRole(auth()->user(), 'admin');
        $this->get(CalculatorResource::getUrl('index'))->assertForbidden();
        $this->get(CalculatorResource::getUrl('edit', ['record' => $record]))->assertForbidden();
        Livewire::test(ListCalculatorRuleSets::class)->assertForbidden();
        $this->assertFalse(CalculatorResource::canAccess());
    }

    public function test_a_new_calculator_version_is_a_draft_copy_and_the_test_panel_compares_it(): void
    {
        $v1 = CalculatorRuleSet::query()->where('calculator', 'gratuity')->where('version', 1)->firstOrFail();
        Livewire::test(ListCalculatorRuleSets::class)
            ->callAction('newVersion_gratuity', data: ['effective_from' => '2027-01-01'])
            ->assertHasNoActionErrors();
        $v2 = CalculatorRuleSet::query()->where('calculator', 'gratuity')->where('version', 2)->firstOrFail();
        $this->assertSame('draft', $v2->status);
        $this->assertSame(Publisher::live($v1)['rules'], Publisher::live($v2)['rules'], 'a copy of the latest version');
        $this->assertSame('2027-01-01', Publisher::live($v2)['effective_from']);
        $this->assertSame(1, (int) content()->ruleSet('gratuity')['version'], 'the site keeps version 1');

        $page = Livewire::test(EditCalculatorRuleSet::class, ['record' => $v2->getRouteKey()])
            ->fillForm(['rules.days_first_years' => 25])
            ->assertSeeHtml('cms-calc-diff')
            ->call('save')
            ->assertHasNoFormErrors();
        $rules = Publisher::live($v2->refresh())['rules'];
        $this->assertSame(25, $rules['days_first_years']);
        $this->assertSame(array_keys(Publisher::live($v1)['rules']), array_keys($rules), 'the stored key order is kept');
        $page->fillForm(['rules.in_scope' => []])->call('save')->assertHasFormErrors(['rules.in_scope']);
    }

    // ------------------------------------------------------------------ SEO

    public function test_the_seo_defaults_keep_todays_output_and_apply_when_set(): void
    {
        $this->get(Seo::getUrl())->assertOk()->assertSee('P01');
        $this->assertNull(SeoMeta::make('A title')['description'], 'no default description today');
        $this->assertTrue(SeoMeta::make('A title')['index']);

        Livewire::test(Seo::class)
            ->fillForm(['default_description.en' => 'A default description.', 'robots_index' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $settings = app(SeoSettings::class)->refresh();
        $this->assertSame(['en' => 'A default description.'], $settings->default_description);
        $this->assertNull($settings->title_suffix);
        app()->forgetScopedInstances();
        $this->assertSame('A default description.', SeoMeta::make('A title')['description']);
        $this->assertSame('Own', SeoMeta::make('A title', 'Own')['description'], 'a page\'s own description wins');
        $this->assertFalse(SeoMeta::make('A title')['index']);
    }

    // ------------------------------------------------------------------ settings

    public function test_saving_whatsapp_changes_every_whatsapp_link_of_the_page(): void
    {
        Livewire::test(WhatsApp::class)
            ->fillForm(['draft.en' => 'Hello Summit, a test message from the settings screen.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $expected = 'https://wa.me/971524666191?text='.rawurlencode('Hello Summit, a test message from the settings screen.');
        foreach (['/en/', '/en/contact/'] as $url) {
            preg_match_all('/href="(https:\/\/wa\.me\/[^"]+)"/', $this->get2($url), $m);
            $this->assertNotEmpty($m[1], $url);
            $this->assertSame([$expected], array_values(array_unique(array_map('html_entity_decode', $m[1]))), "{$url}: every WhatsApp link opens the one WA01 draft");
        }
        preg_match_all('/href="(https:\/\/wa\.me\/[^"]+)"/', $this->get2('/ar/'), $m);
        $this->assertStringNotContainsString(rawurlencode('Hello Summit, a test'), implode(' ', $m[1]), 'Arabic keeps its own draft');
        $this->assertSame($expected, WhatsApp::link('Hello Summit, a test message from the settings screen.'), 'the preview is the link the site builds');

        Livewire::test(WhatsApp::class)->fillForm(['draft.ar' => ''])->call('save')->assertHasFormErrors(['draft.ar']);
    }

    public function test_saving_office_hours_changes_the_hours_payload(): void
    {
        $hours = fn () => json_decode(preg_match('#<script type="application/json" id="d2-hours-copy">(.*?)</script>#s', $this->get2('/en/'), $m) ? $m[1] : 'null', true);
        $this->assertSame(['timeZone' => 'Asia/Dubai', 'days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00'], $hours()['hours']);

        Livewire::test(OfficeHours::class)
            ->fillForm(['days' => ['1', '2', '3', '4', '6'], 'open' => '10:00', 'close' => '17:30', 'closures' => [['date' => '2026-12-02']], 'review_target_hours' => 24])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['timeZone' => 'Asia/Dubai', 'days' => [1, 2, 3, 4, 6], 'open' => '10:00', 'close' => '17:30'], $hours()['hours']);
        $this->assertStringNotContainsString('review_target', $this->get2('/en/'), 'the review target is never public');

        Livewire::test(OfficeHours::class)->fillForm(['open' => '18:00', 'close' => '09:00'])->call('save')->assertHasFormErrors(['close']);
    }

    public function test_the_contact_settings_validate_the_link_forms(): void
    {
        Livewire::test(GeneralContact::class)
            ->assertFormSet(['phone_display' => '+971 52 466 6191', 'phone_href' => 'tel:+971524666191'])
            ->fillForm(['phone_href' => 'tel:+971500000000'])
            ->call('save')
            ->assertHasFormErrors(['phone_href']);
        Livewire::test(GeneralContact::class)->fillForm(['email_href' => 'mailto:other@example.com'])->call('save')->assertHasFormErrors(['email_href']);
        Livewire::test(GeneralContact::class)->fillForm(['whatsapp_number' => '+971524666191'])->call('save')->assertHasFormErrors(['whatsapp_number']);
        Livewire::test(GeneralContact::class)->call('save')->assertHasNoFormErrors();
    }
}
