<?php

namespace Tests\Feature;

use App\Filament\Cms\Support\CmsAccess;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\ConsultationForm;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 7 (docs/briefs/client-final-changes-2026-11-25.md, owner decision 1): no consultation form anywhere on
 * the public site, every "Request a Legal Consultation" and every no-result message leads to WhatsApp (the approved
 * message, a new tab, never sent), the enquiry endpoint is gone, and the admin hides the requests inbox, the request
 * reports, the Forms and Consent and Notifications settings and their scheduled jobs. The code and the enquiries table
 * stay (config summit.consultation_form brings them back; the form's own tests switch it on).
 */
final class WhatsAppOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return list<string> every public page of one language */
    private function paths(string $locale): array
    {
        $paths = ["/{$locale}/", "/{$locale}/calculators/", "/{$locale}/styleguide/", "/{$locale}/offline/"];
        foreach (config('summit.pages') as $code => $page) {
            if ($code !== 'P01') {
                $paths[] = "/{$locale}/".$page['path'];
            }
        }
        foreach (config('summit.services') as $slug) {
            $paths[] = "/{$locale}/practice-areas/{$slug}/";
        }

        return $paths;
    }

    public function test_the_form_is_off_by_default(): void
    {
        $this->assertFalse(ConsultationForm::enabled());
        $this->assertFalse((bool) config('summit.consultation_form'));
    }

    public function test_no_public_page_has_consultation_form_markup(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach ($this->paths($locale) as $path) {
                $html = $this->get($path)->assertOk()->getContent();
                foreach (['data-wizard-form', 'data-wizard', '/enquiries', 'method="post"', 'type="file"', 'name="full_name"', 'name="summary"',
                    'name="consent"', 'cf-turnstile', 'data-hero-tool'] as $needle) {
                    $this->assertStringNotContainsString($needle, $html, "{$path}: {$needle}");
                }
                $this->assertDoesNotMatchRegularExpression('/<(input|textarea)\b[^>]*\bname="(name|email|phone|message)"/i', $html, "{$path}: a contact field");
            }
        }
    }

    public function test_no_form_wording_is_shown(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (["/{$locale}/", "/{$locale}/contact/", "/{$locale}/offline/", "/{$locale}/faq/"] as $path) {
                $html = $this->get($path)->assertOk()->getContent();
                foreach (['form.heading', 'form.intro', 'form.required_note', 'form.submit'] as $key) {
                    $this->assertStringNotContainsString(e(ui($key, $locale)), $html, "{$path}: ui {$key}");
                }
                $this->assertStringNotContainsString(e(d2p('palette.no_results_hint', [], $locale)), $html, "{$path}: the palette's form hint");
            }
        }
        $html = $this->get('/en/')->getContent();
        $this->assertStringNotContainsString('Nothing is sent until you submit', $html);
        $this->assertStringNotContainsString('consultation form', $this->get('/en/offline/')->getContent());
        // The Contact page keeps its approved sections, without "Send a Consultation Request" (it led into the form).
        $contact = $this->get('/en/contact/')->getContent();
        $this->assertStringNotContainsString('Send a Consultation Request', $contact);
        $this->assertStringContainsString('What Helps Us Understand Your Matter', $contact);
        $this->assertStringContainsString('id="contact-helps"', $contact);
    }

    /**
     * The approved texts that named the form (client layer, PDF item 7): only the form clause is cut. The cookie texts'
     * "protect the form" stays for now (owner decision 5).
     */
    public function test_approved_texts_no_longer_name_the_form(): void
    {
        $phrases = [
            'en' => ['the form', 'a form or', 'Forms and', 'send a consultation request', 'add it to a consultation request'],
            'ar' => ['عبر النموذج', 'بما فيها النموذج', 'نموذج الاستشارة', 'إرسال النموذج', 'إتاحة نموذج', 'النماذج والتواصل', 'أو أرسل طلب استشارة', 'إلى طلب استشارة'],
            'ru' => ['через форму', 'Через форму', 'В форме запроса', 'отправка формы', 'Наличие формы', 'Формы и', 'отправьте запрос на консультацию', 'в запрос на консультацию'],
        ];
        foreach ($phrases as $locale => $needles) {
            $kept = [ui('cookies.banner_text', $locale), ui('cookies.necessary_text', $locale), content()->page('L01', $locale)['content']['sections'][7]['blocks'][0]['text']];
            foreach (['', 'contact/', 'privacy-policy/', 'legal-disclaimer/', 'terms-and-conditions/', 'calculators/'] as $path) {
                $html = str_replace($kept, '', html_entity_decode((string) $this->get("/{$locale}/{$path}")->assertOk()->getContent()));
                foreach ($needles as $needle) {
                    $this->assertStringNotContainsString($needle, $html, "/{$locale}/{$path}: {$needle}");
                }
            }
        }
        foreach (['P07', 'P08', 'L01', 'L02'] as $code) {
            $this->assertSame(\App\Content\Cms\CopySource::CLIENT_APPROVED, \App\Models\Content\Page::query()->findOrFail($code)->copy_source, $code);
        }
        $this->assertSame('Contact Summit Legal Consultancy by telephone, WhatsApp or email in Arabic, English or Russian with details of your matter.', content()->page('P07', 'en')['meta_description']);
    }

    public function test_every_consultation_action_opens_whatsapp_in_a_new_tab(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $wa = (string) setting('whatsapp_url', $locale);
            $this->assertStringStartsWith('https://wa.me/971524666191?text=', $wa);
            $this->assertSame($wa, SiteUrl::consultation($locale));
            $this->assertSame($wa, SiteUrl::target('A01', $locale));
            $this->assertSame($wa, \App\Design\D2\Prefill::url(['service' => 'S06', 'situation' => 'S06-2'], $locale));
            // "Contact Us" opens the Contact page (released with the form gone).
            $this->assertSame(SiteUrl::page('P07', $locale), SiteUrl::contact($locale));

            foreach (["/{$locale}/", "/{$locale}/practice-areas/labor-law/", "/{$locale}/calculators/", "/{$locale}/case-studies/", "/{$locale}/about/"] as $path) {
                $html = $this->get($path)->getContent();
                $label = e(ui('cta.request_consultation', $locale));
                preg_match_all('/<a\b([^>]*)>((?:(?!<\/a>).)*'.preg_quote($label, '/').'(?:(?!<\/a>).)*)<\/a>/su', $html, $links, PREG_SET_ORDER);
                $this->assertNotEmpty($links, "{$path}: a Request a Legal Consultation link");
                foreach ($links as [, $attributes, $body]) {
                    $this->assertStringContainsString('href="'.e($wa).'"', $attributes, "{$path}: the link opens WhatsApp");
                    $this->assertStringContainsString('target="_blank"', $attributes, $path);
                    $this->assertStringContainsString('rel="noopener noreferrer"', $attributes, $path);
                    $this->assertStringContainsString('data-brand="whatsapp"', $body, "{$path}: the WhatsApp mark");
                    // PDF item 32: the accessible name says where it goes (the brand's name, visually hidden)
                    $this->assertStringContainsString('<span class="sr-only"> – '.($locale === 'ar' ? 'واتساب' : 'WhatsApp').'</span>', $body, "{$path}: WhatsApp in the name");
                }
            }
        }
    }

    public function test_the_home_contact_section_leads_to_whatsapp_and_the_start_block_is_gone(): void
    {
        $html = $this->get('/en/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<section[^>]*contact-section--whatsapp[^>]*id="consultation-form"/', $html);
        $this->assertStringContainsString('Start with Clear Legal Advice', $html);
        $this->assertStringNotContainsString('id="start"', $html);
        $this->assertFalse(collect(\App\Content\Cms\HomeBlocks::BLOCKS)->firstWhere('type', 'start')['enabled']);
    }

    public function test_no_result_messages_offer_whatsapp(): void
    {
        $wa = e((string) setting('whatsapp_url', 'en'));
        $html = $this->get('/en/')->getContent();
        // The search palette: "Contact Us on WhatsApp" instead of "Describe your matter".
        $this->assertMatchesRegularExpression('/<a[^>]*id="palette-o-whatsapp"[^>]*href="'.preg_quote($wa, '/').'"[^>]*target="_blank"/', $html);
        $this->assertStringNotContainsString('id="palette-o-describe"', $html);
        $this->assertStringContainsString(e(d2p('palette.no_results_hint_whatsapp')), $html);
        // The FAQ search and the Matter Navigator's situation search.
        $this->assertMatchesRegularExpression('/<a[^>]*href="'.preg_quote($wa, '/').'"[^>]*data-faq-whatsapp/', $this->get('/en/faq/')->getContent());
        $this->assertMatchesRegularExpression('/class="nav-empty"[\s\S]*?href="'.preg_quote($wa, '/').'"/', $html);
    }

    public function test_the_enquiry_endpoint_answers_404_and_stores_nothing(): void
    {
        $this->post('/en/enquiries', ['full_name' => 'Sample Client', 'phone' => '+971500000000', 'summary' => 'A test', 'consent' => '1'])
            ->assertNotFound();
        $this->postJson('/ar/enquiries', ['full_name' => 'Sample Client'])->assertNotFound();
        $this->assertSame(0, Enquiry::query()->withTrashed()->count());
    }

    public function test_the_admin_hides_the_requests_screens_from_every_role(): void
    {
        $user = $this->grantRole(User::factory()->create());
        $this->actingAs($user);
        foreach (ConsultationForm::REQUEST_SCREENS as $screen) {
            $this->assertFalse(CmsAccess::can($screen, 'view'), $screen);
        }
        $this->assertTrue(CmsAccess::can('pages', 'view'));

        $this->get('/admin/requests')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/settings/forms')->assertForbidden();
        $this->get('/admin/settings/notifications')->assertForbidden();

        $dashboard = $this->get('/admin')->assertOk()->getContent();
        foreach (['/admin/requests', '/admin/reports', '/admin/settings/forms', '/admin/settings/notifications'] as $url) {
            $this->assertStringNotContainsString('href="'.url($url).'"', $dashboard, $url);
        }
        $this->assertSame([\App\Filament\Widgets\ContentStatus::class], (new \App\Filament\Pages\Dashboard)->getWidgets());

        // The kept code: switched on, the screens come back for the super admin.
        config(['summit.consultation_form' => true]);
        $this->assertTrue(CmsAccess::can('requests', 'view'));
        $this->assertTrue(CmsAccess::can('reports', 'view'));
    }

    public function test_the_scheduler_runs_no_request_jobs(): void
    {
        $this->artisan('schedule:list')->assertSuccessful(); // loads routes/console.php
        $names = collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->description)->all();
        $this->assertNotEmpty($names);
        foreach (['requests-retention', 'requests-digest', 'requests-overdue'] as $job) {
            $this->assertNotContains($job, $names);
        }
        $this->assertContains('cms-publish-scheduled', $names);
    }
}
