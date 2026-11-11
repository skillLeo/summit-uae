<?php

namespace Tests\Feature\Design\D2;

use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design 2 consultation wizard (home #consultation-form; spec §12 C14) on the unchanged enquiry backend
 * (docs/enquiries.md): the same action, field names, idempotency key and honeypot in every language,
 * and the plain no-JS form is accepted by the existing endpoint and renders every outcome it flashes.
 * Turnstile's siteverify is faked; no request leaves the test.
 */
final class WizardTest extends TestCase
{
    use RefreshDatabase;

    /** The endpoint's own field names (StoreEnquiryRequest + technical inputs). */
    private const POSTED = ['_token', 'idempotency_key', 'website', 'name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date', 'deadline_details', 'enquiry_consent'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        config([
            'summit.turnstile.enabled' => true,
            'summit.features.attachments_enabled' => false,
            'summit.enquiries.inbox' => 'inbox@summit.test',
        ]);
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([config('summit.turnstile.verify_url') => Http::response(['success' => true, 'error-codes' => []])]);
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'ru' => ['ru']];
    }

    private function home(string $locale, string $query = ''): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/{$query}")->assertOk()->getContent();
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function one(DOMXPath $x, string $query): DOMElement
    {
        $nodes = $x->query($query);
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "exactly one {$query}");
        $node = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    /**
     * What a browser without JavaScript posts: every named control of the form that the form owns
     * (controls with a form="…" attribute pointing elsewhere are not submitted), unchecked boxes left out.
     *
     * @param  array<string, mixed>  $typed  the visitor's values (an UploadedFile for a file input)
     * @return array<string, mixed>
     */
    private function plainSubmission(DOMXPath $x, array $typed): array
    {
        $form = $this->one($x, '//form[@data-wizard-form]');
        $payload = [];
        foreach ($x->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $control) {
            /** @var DOMElement $control */
            if ($control->hasAttribute('form')) {
                continue;
            }
            $name = $control->getAttribute('name');
            $type = strtolower($control->getAttribute('type'));
            if ($type === 'file') {
                // A browser posts a file input only with a chosen file (multipart): the caller passes it.
                if (array_key_exists($name, $typed)) {
                    $payload[$name] = $typed[$name];
                }

                continue;
            }
            if ($control->tagName === 'select') {
                $selected = $x->query('.//option[@selected]', $control)->item(0);
                $value = $selected instanceof DOMElement ? $selected->getAttribute('value') : '';
            } elseif ($control->tagName === 'textarea') {
                $value = $control->textContent;
            } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                if (! array_key_exists($name, $typed) && ! $control->hasAttribute('checked')) {
                    continue;
                }
                $value = $control->getAttribute('value');
            } else {
                $value = $control->getAttribute('value');
            }
            $payload[$name] = array_key_exists($name, $typed) ? $typed[$name] : $value;
        }

        return $payload;
    }

    // ------------------------------------------------------------------ render

    #[DataProvider('locales')]
    public function test_the_design_two_home_renders_the_wizard_on_the_enquiry_endpoint(string $locale): void
    {
        $x = $this->dom($this->home($locale));

        $section = $this->one($x, '//section[@id="consultation-form"]');
        $heading = $this->one($x, '//section[@id="consultation-form"]//h2[@id="'.$section->getAttribute('aria-labelledby').'"]');
        $this->assertSame(content()->page('P01', $locale)['content']['contact']['heading'], trim($heading->textContent));

        $form = $this->one($x, '//section[@id="consultation-form"]//form[@data-wizard-form]');
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(url("/{$locale}/enquiries"), $form->getAttribute('action'));
        $this->assertTrue($form->hasAttribute('novalidate'));
        $this->assertSame('d2Wizard', $this->one($x, '//div[@data-wizard]')->getAttribute('x-data'));

        // The posted field set is exactly the endpoint's (nothing hidden, no upload while attachments are off).
        $names = [];
        foreach ($x->query('.//*[@name][not(@form)]', $form) as $control) {
            $names[] = $control->getAttribute('name');
        }
        $this->assertEqualsCanonicalizing(self::POSTED, array_values(array_unique($names)));
        $this->assertSame(0, $x->query('//input[@type="file"] | //*[@name="attachment"]')->length);

        $key = $this->one($x, '//form[@data-wizard-form]//input[@name="idempotency_key"]');
        $this->assertSame('hidden', $key->getAttribute('type'));
        $this->assertTrue(Str::isUuid($key->getAttribute('value')));
        $this->assertSame('off', $key->getAttribute('autocomplete'));

        $trap = $this->one($x, '//form[@data-wizard-form]//input[@name="website"]');
        $this->assertSame('-1', $trap->getAttribute('tabindex'));
        $this->assertSame('off', $trap->getAttribute('autocomplete'));
        $this->assertSame('', $trap->getAttribute('value'));
        $this->assertSame(1, $x->query('ancestor::*[@aria-hidden="true"]', $trap)->length, 'the honeypot is hidden from assistive technology');

        // The homepage file's own form copy (owner decision; P01 form_home_variant) and its option sets: the eleven
        // Section 02 service titles between "Choose a Matter Type" and "Other Matter" (homepage note 0250).
        $variant = content()->page('P01', $locale)['content']['form_home_variant'];
        foreach (['name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date'] as $field) {
            $this->assertSame(1, $x->query('.//label[normalize-space()="'.$variant['labels'][$field].'*" or normalize-space()="'.$variant['labels'][$field].'"]', $form)->length, "{$locale}: {$field} label");
        }
        $this->assertStringContainsString($variant['deadline_hint'], $form->textContent);
        $this->assertStringContainsString($variant['sensitive_notice'], $form->textContent);
        $this->assertStringContainsString($variant['engagement_notice'], $form->textContent);
        $this->assertStringContainsString($variant['submit'], $form->textContent);
        $matter = [];
        foreach ($x->query('.//select[@name="matter_type"]/option', $form) as $option) {
            $matter[$option->getAttribute('value')] = trim($option->textContent);
        }
        $titles = array_map(fn ($code) => content()->service($code, $locale)['title'], array_keys(config('summit.services')));
        $this->assertSame(['', ...array_keys(config('summit.services')), 'other'], array_keys($matter));
        $this->assertSame([$variant['matter_placeholder'], ...$titles, $variant['matter_other']], array_values($matter));
        $languages = [];
        foreach ($x->query('.//select[@name="preferred_language"]/option', $form) as $option) {
            $languages[$option->getAttribute('value')] = trim($option->textContent);
        }
        $this->assertSame(['' => $variant['language_placeholder'], ...$variant['language_options']], $languages);
        // The Contact page's guidance lines and heading are not repeated on the home (the homepage file has none).
        $wizardText = $this->one($x, '//div[@data-wizard]')->textContent;
        foreach (['name.help', 'phone.help', 'email.help', 'summary.help', 'contact_group.help', 'heading', 'intro', 'required_note'] as $key) {
            $this->assertStringNotContainsString(ui('form.'.$key, $locale), $wizardText, "{$locale}: {$key}");
        }
        $this->assertSame('contact-title', $form->getAttribute('aria-labelledby'), 'section 09\'s H2 names the form');

        // Consent: unchecked, required for assistive technology, never invalid before a submit.
        $consent = $this->one($x, '//input[@name="enquiry_consent"]');
        $this->assertFalse($consent->hasAttribute('checked'));
        $this->assertSame('true', $consent->getAttribute('aria-required'));
        $this->assertFalse($consent->hasAttribute('aria-invalid'));

        // Wizard-only controls are never posted (form="…-local": the backend has no field for them).
        foreach (['//input[@data-wizard-audience]', '//select[@data-wizard-situation]', '//input[@data-wizard-doc]', '//textarea[@data-wizard-message]'] as $query) {
            $nodes = $x->query($query);
            $this->assertGreaterThan(0, $nodes->length, $query);
            foreach ($nodes as $node) {
                $this->assertSame('wz-local', $node->getAttribute('form'), $query);
            }
        }

        // Five step templates for the Phase A step chips, and the data the controller reads.
        $this->assertSame(5, $x->query('//template[@data-wizard-steps-for]')->length);
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($locale, $data['locale']);
        $this->assertSame(url("/{$locale}/enquiries"), $data['endpoint']);
        $this->assertSame('summit:draft:'.$locale, $data['draftKey']);
        $this->assertSame((string) config('summit.turnstile.site_key'), $data['turnstile']);
        $services = json_decode($this->one($x, '//script[@id="'.$data['servicesRef'].'"]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys(config('summit.services')), array_keys($services));
        $this->assertSame(content()->service('S06', $locale)['documents'], $services['S06']['documents']);
        $this->assertSame(content()->service('S06', $locale)['situations'], $services['S06']['situations']);
    }

    public function test_every_render_carries_a_fresh_idempotency_key(): void
    {
        $first = $this->one($this->dom($this->home('en')), '//input[@name="idempotency_key"]')->getAttribute('value');
        $second = $this->one($this->dom($this->home('en')), '//input[@name="idempotency_key"]')->getAttribute('value');
        $this->assertNotSame($first, $second);
    }

    #[DataProvider('locales')]
    public function test_the_contact_card_shows_the_approved_channels_and_no_address(string $locale): void
    {
        $x = $this->dom($this->home($locale));
        $card = $this->one($x, '//section[@id="consultation-form"]//div[contains(concat(" ", @class, " "), " contact-card ")]');
        $contact = content()->page('P01', $locale)['content']['contact'];

        $this->assertSame(1, $x->query('.//a[@href="tel:+971524666191"]', $card)->length);
        $this->assertSame(1, $x->query('.//a[@href="mailto:info@summitlegaluae.com"]', $card)->length);
        $whatsapp = $this->one($x, '//section[@id="consultation-form"]//div[contains(@class, "contact-card")]//a[starts-with(@href, "https://wa.me/971524666191")]');
        $this->assertSame(setting('whatsapp_url', $locale), $whatsapp->getAttribute('href'));
        $this->assertSame('_blank', $whatsapp->getAttribute('target'));
        foreach ($x->query('.//bdi', $card) as $bdi) {
            $this->assertSame('ltr', $bdi->getAttribute('dir'));
        }
        // the approved hours line, each time bound to its am/pm by a no-break space (App\Design\D2\Hours::keepTimes())
        $this->assertStringContainsString(\App\Design\D2\Hours::keepTimes($contact['hours']), $card->textContent);
        $this->assertStringContainsString($contact['languages'], $card->textContent);
        $this->assertSame(1, $x->query('.//*[@data-hours-status]', $card)->length);
        $this->assertSame(0, $x->query('.//iframe | .//address', $card)->length);
    }

    /**
     * Design 2.1, Phase 1 (brief §3.5, §3.6 rule 5, §3.8; D04, D10, owner decision on the hours): the contact
     * card shows the home page file's own hours sentence ("Business hours: Monday to Friday, 9 am to 6 pm." in
     * each language), functional icons only (phone, e-mail, the contact block's clock, the external mark) with
     * no decorative globe before the languages, and the WhatsApp row draws the WhatsApp mark through the icon
     * component (Meta's official file, or the marked placeholder until it is supplied), never a drawn glyph.
     */
    #[DataProvider('locales')]
    public function test_design_2_1_contact_card_icons_and_the_home_hours_sentence(string $locale): void
    {
        $x = $this->dom($this->home($locale));
        $card = $this->one($x, '//section[@id="consultation-form"]//div[contains(concat(" ", @class, " "), " contact-card ")]');
        $markup = $card->ownerDocument->saveHTML($card);

        $hours = content()->page('P01', $locale)['content']['contact']['hours'];
        $this->assertSame([
            'en' => 'Business hours: Monday to Friday, 9 am to 6 pm.',
            'ar' => 'ساعات العمل: الاثنين إلى الجمعة، من 9 صباحًا إلى 6 مساءً.',
            'ru' => 'Часы работы: с понедельника по пятницу, с 9:00 до 18:00.',
        ][$locale], $hours, 'the homepage DOCX line');
        // printed with each time bound to its am/pm (D11: "9 / am" never splits; whitespace only)
        $this->assertStringContainsString(\App\Design\D2\Hours::keepTimes($hours), $card->textContent);

        preg_match_all('/data-icon="([^"]+)"/', $markup, $m);
        $icons = array_values(array_unique($m[1]));
        sort($icons);
        $this->assertSame(['clock', 'external', 'mail', 'phone', 'whatsapp'], $icons, "{$locale}: functional icons and the WhatsApp mark only");
        $this->assertStringNotContainsString('data-icon="globe"', $markup);
        $whatsapp = $this->one($x, '//section[@id="consultation-form"]//div[contains(@class, "contact-card")]//a[starts-with(@href, "https://wa.me/971524666191")]');
        $this->assertSame(1, $x->query('.//*[@data-brand="whatsapp"]', $whatsapp)->length, 'the WhatsApp mark (Meta\'s file or the marked placeholder)');
        $this->assertSame(0, $x->query('.//*[contains(concat(" ", @class, " "), " icon ")][@data-icon="whatsapp"]', $whatsapp)->length, 'never a drawn glyph');
    }

    /**
     * Design 2.1, Phase 1 (typography of approved copy): the Contact page's approved languages line keeps its own
     * " · " separators (approved copy is never rewritten), and the card binds each dot to the word before it with a
     * no-break space, so a wrapped line never starts with "·" (Russian broke before "· русский" at 1440). Only the
     * whitespace differs: the text read with ordinary spaces is the approved line.
     */
    #[DataProvider('locales')]
    public function test_the_contact_card_keeps_approved_separators_with_the_word_before_them(string $locale): void
    {
        app()->setLocale($locale);
        $details = content()->page('P07', $locale)['content']['details'];
        $this->assertStringContainsString(' · ', $details['languages'], 'the approved Contact line uses separators');

        $html = \Illuminate\Support\Facades\Blade::render('<x-d2.contact.card :contact="$contact" id-prefix="sep" />', [
            'contact' => ['hours' => $details['hours'], 'languages' => $details['languages']],
        ]);
        $bound = e(str_replace(' · ', "\u{00A0}· ", $details['languages']));
        $this->assertStringContainsString('<span class="contact-card__fact-text">'.$bound.'</span>', $html);
        $this->assertStringNotContainsString(' · ', $html, 'no breakable space before a separator');
        $this->assertStringContainsString(e($details['languages']), str_replace("\u{00A0}", ' ', $html), 'the approved words unchanged');
    }

    // ------------------------------------------------------------------ without JavaScript

    #[DataProvider('locales')]
    public function test_a_no_js_submission_with_the_design_two_field_set_is_accepted(string $locale): void
    {
        // Without JavaScript there is no Turnstile token; with Turnstile off, the plain form alone is enough.
        config(['summit.turnstile.enabled' => false]);
        $payload = $this->plainSubmission($this->dom($this->home($locale)), [
            'name' => 'A. Example',
            'phone' => '+971 50 000 0000',
            'email' => 'a.example@example.com',
            'preferred_language' => 'ru',
            'matter_type' => 'S06',
            'summary' => "My final entitlements look wrong.\nThe contract ended last month.",
            'enquiry_consent' => '1',
        ]);
        $this->assertEqualsCanonicalizing(self::POSTED, array_keys($payload));

        $response = $this->post("/{$locale}/enquiries", $payload, ['Accept' => 'text/html']);

        $response->assertStatus(303);
        $this->assertSame("/{$locale}/", parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame('consultation-form', parse_url((string) $response->headers->get('Location'), PHP_URL_FRAGMENT));
        $response->assertSessionHas('enquiry_status', 'received');
        $enquiry = Enquiry::sole();
        $this->assertSame($locale, $enquiry->locale);
        $this->assertSame('S06', $enquiry->matter_type);
        $this->assertSame('+971500000000', $enquiry->phone);
        $this->assertSame('ru', $enquiry->preferred_language);
        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->status);
        $this->assertSame(strtolower($payload['idempotency_key']), $enquiry->idempotency_key);

        // The redirect target renders the received state with the reference, and no form.
        $x = $this->dom($this->home($locale));
        $success = $this->one($x, '//*[@data-wizard-success]');
        $this->assertFalse($success->hasAttribute('hidden'));
        $this->assertSame($enquiry->reference, trim($this->one($x, '//*[@data-wizard-reference]')->textContent));
        $this->assertStringContainsString(content()->page('P01', $locale)['content']['form_home_variant']['states']['success'], $success->textContent, 'the homepage file\'s success message');
        $this->assertTrue($this->one($x, '//form[@data-wizard-form]')->hasAttribute('hidden'));
    }

    public function test_the_json_path_accepts_the_same_field_set_with_a_turnstile_token(): void
    {
        $payload = $this->plainSubmission($this->dom($this->home('en')), [
            'name' => 'A. Example',
            'email' => 'a.example@example.com',
            'summary' => "Summary.\n\nContacting us as: Individual\nDocuments I have:\n- The employment contract.",
            'enquiry_consent' => '1',
        ]) + ['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'];

        $response = $this->post('/en/enquiries', $payload, ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('status', 'received');
        $this->assertSame($payload['summary'], Enquiry::sole()->summary);
    }

    public function test_without_a_token_a_no_js_post_fails_safely_and_the_values_and_channels_stay(): void
    {
        $payload = $this->plainSubmission($this->dom($this->home('ar')), [
            'name' => 'A. Example',
            'phone' => '+971 50 000 0000',
            'summary' => 'Summary.',
            'enquiry_consent' => '1',
        ]);

        $this->post('/ar/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303)->assertSessionHas('enquiry_status', 'failed');
        $this->assertSame(0, Enquiry::count());

        $x = $this->dom($this->home('ar'));
        $notice = $this->one($x, '//*[@data-wizard-notice="failed"]');
        $this->assertFalse($notice->hasAttribute('hidden'));
        $this->assertStringContainsString(content()->page('P01', 'ar')['content']['form_home_variant']['states']['failure'], $notice->textContent, 'the homepage file\'s failure message');
        $this->assertSame(1, $x->query('.//a[@href="tel:+971524666191"]', $notice)->length);
        $this->assertSame(1, $x->query('.//a[starts-with(@href, "https://wa.me/")]', $notice)->length);
        $this->assertSame('A. Example', $this->one($x, '//input[@name="name"]')->getAttribute('value'));
        $this->assertSame('Summary.', $this->one($x, '//textarea[@name="summary"]')->textContent);
        // Never the old key: a fresh one is rendered.
        $this->assertNotSame($payload['idempotency_key'], $this->one($x, '//input[@name="idempotency_key"]')->getAttribute('value'));
    }

    public function test_no_js_validation_errors_render_on_their_fields_with_a_linked_summary_in_form_order(): void
    {
        $payload = $this->plainSubmission($this->dom($this->home('en')), ['name' => '', 'phone' => '+971 ', 'email' => '', 'summary' => '']);
        // (No assertSessionHas here: in the test client it re-marshals the JSON-serialised error bag in memory,
        // and the next request of the same test would then read an empty bag. Browsers never see that.)
        $this->post('/en/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303);

        $html = $this->home('en');
        $x = $this->dom($html);
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('invalid', $data['initial']['status']);
        $this->assertEqualsCanonicalizing(['name', 'contact', 'summary', 'enquiry_consent'], array_keys($data['initial']['errors']));
        $summary = $this->one($x, '//*[@data-wizard-notice="errors"]');
        $this->assertFalse($summary->hasAttribute('hidden'));
        $this->assertStringContainsString(ui('form.state.summary'), $summary->textContent);
        $links = [];
        foreach ($x->query('.//a', $summary) as $a) {
            $links[] = $a->getAttribute('href');
        }
        // name, contact (→ the phone input), summary, enquiry_consent (→ the consent checkbox): all real ids.
        $this->assertSame(['#wz-name', '#wz-phone', '#wz-summary', '#wz-consent'], $links);
        foreach ($links as $href) {
            $this->assertSame(1, $x->query('//*[@id="'.substr($href, 1).'"]')->length, $href);
        }

        $name = $this->one($x, '//input[@name="name"]');
        $this->assertSame('true', $name->getAttribute('aria-invalid'));
        $this->assertStringContainsString('wz-name-error', $name->getAttribute('aria-describedby'));
        $this->assertFalse($this->one($x, '//*[@id="wz-name-error"]')->hasAttribute('hidden'));
        $this->assertFalse($this->one($x, '//*[@id="wz-contact-error"]')->hasAttribute('hidden'));
        $this->assertSame('true', $this->one($x, '//input[@name="phone"]')->getAttribute('aria-invalid'));
        $this->assertSame('true', $this->one($x, '//input[@name="enquiry_consent"]')->getAttribute('aria-invalid'));
        // A field without an error stays quiet (its error id is not a description).
        $this->assertStringNotContainsString('wz-deadline-date-error', (string) $this->one($x, '//input[@name="deadline_date"]')->getAttribute('aria-describedby'));
    }

    public function test_the_rate_limited_flash_renders_its_notice_with_the_channels(): void
    {
        $x = $this->dom($this->withSession(['enquiry_status' => 'rate_limited'])->withUnencryptedCookies(['summit_design' => '2'])->get('/ru/')->assertOk()->getContent());
        $notice = $this->one($x, '//*[@data-wizard-notice="rate_limited"]');
        $this->assertFalse($notice->hasAttribute('hidden'));
        $this->assertStringContainsString(ui('form.state.rate_limited', 'ru'), $notice->textContent);
        $this->assertSame(1, $x->query('.//a[@href="tel:+971524666191"]', $notice)->length);
    }

    public function test_array_shaped_old_input_and_query_values_never_break_the_page(): void
    {
        $this->withSession(['_old_input' => ['name' => ['x'], 'summary' => ['y'], 'phone' => ['z'], 'matter_type' => ['S06']]])
            ->withUnencryptedCookies(['summit_design' => '2'])
            ->get('/en/?service[]=S06&situation[]=S06-1&consultant[]=team-ahmed')
            ->assertOk();
    }

    // ------------------------------------------------------------------ prefill

    public function test_prefill_selects_the_service_and_writes_the_context_into_the_summary_without_javascript(): void
    {
        $x = $this->dom($this->home('en', '?service=S06&situation=S06-2&audience=business&consultant=team-ahmed&tool=gratuity'));

        $this->assertSame('S06', $this->one($x, '//select[@name="matter_type"]/option[@selected]')->getAttribute('value'));
        $this->assertSame('S06-2', $this->one($x, '//select[@data-wizard-situation]/option[@selected]')->getAttribute('value'));
        $this->assertTrue($this->one($x, '//input[@data-wizard-audience][@value="business"]')->hasAttribute('checked'));

        $summary = $this->one($x, '//textarea[@name="summary"]')->textContent;
        $situation = content()->service('S06')['situations'][1];
        $this->assertStringContainsString(d2p('wizard.context.audience', ['value' => d2p('wizard.audience.business')]), $summary);
        $this->assertStringContainsString(d2p('wizard.context.situation', ['value' => $situation]), $summary);
        $this->assertStringContainsString(d2p('wizard.context.consultant', ['value' => content()->record('team_members', 'team-ahmed')['name']]), $summary);
        $this->assertStringContainsString(d2p('wizard.context.tool', ['value' => d2p('wizard.tool.gratuity')]), $summary);
        // The controller recognises the server-written context by this attribute (and moves it to the review).
        $this->assertSame($summary, $this->one($x, '//textarea[@name="summary"]')->getAttribute('data-context'));
    }

    public function test_unknown_prefill_codes_are_ignored(): void
    {
        $x = $this->dom($this->home('en', '?service=S99&situation=S06-9&audience=alien&consultant=team-nobody&tool=crypto'));

        $this->assertSame('', $this->one($x, '//select[@name="matter_type"]/option[@selected]')->getAttribute('value'));
        $this->assertSame('', trim($this->one($x, '//textarea[@name="summary"]')->textContent));
        $this->assertSame(0, $x->query('//input[@data-wizard-audience][@checked]')->length);
    }

    // ------------------------------------------------------------------ lazy start (no layout change on mount)

    #[DataProvider('locales')]
    public function test_the_stepped_layout_is_server_rendered_for_the_lazy_controller(string $locale): void
    {
        $x = $this->dom($this->home($locale));
        $root = $this->one($x, '//div[@data-wizard]');

        // A fresh visit: the controller loads when the wizard comes near (lazy) and opens on step 1.
        $this->assertSame('lazy', $root->getAttribute('data-wizard-mount'));
        $this->assertSame('1', $root->getAttribute('data-step'));
        $this->assertFalse($root->hasAttribute('data-wizard-specimen'));
        // The restore prompt is decided at start-up from this browser's draft (key and lifetime from here).
        $this->assertTrue($root->hasAttribute('data-wizard-offer-draft'));
        $this->assertSame('summit:draft:'.$locale, $root->getAttribute('data-draft-key'));
        $this->assertSame('7', $root->getAttribute('data-draft-days'));
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(7, $data['draftDays']);

        // Only step 1 shows before the controller mounts: every other step and the submit wait (CSS on html.js).
        $this->assertFalse($this->one($x, '//*[@data-step-panel="1"]')->hasAttribute('data-wizard-later'));
        foreach ([2, 3, 4, 5] as $n) {
            $this->assertTrue($this->one($x, '//*[@data-step-panel="'.$n.'"]')->hasAttribute('data-wizard-later'), "step {$n}");
        }
        $submit = $this->one($x, '//button[@data-wizard-submit]');
        $this->assertTrue($submit->hasAttribute('data-wizard-later'));
        $this->assertFalse($submit->hasAttribute('hidden'), 'without JavaScript the submit button is always there');
        $this->assertTrue($this->one($x, '//button[@data-wizard-back]')->hasAttribute('hidden'));
        $this->assertFalse($this->one($x, '//button[@data-wizard-next]')->hasAttribute('hidden'));
        $steps = $this->one($x, '//*[@data-wizard-steps]//*[contains(concat(" ", @class, " "), " step-list ")]');
        $this->assertSame('1', $steps->getAttribute('data-current'));
        // Without JavaScript every group but the JavaScript-only Documents step is part of the one form.
        foreach ([1, 2, 3, 5] as $n) {
            $this->assertFalse($this->one($x, '//*[@data-step-panel="'.$n.'"]')->hasAttribute('hidden'), "step {$n}");
        }
        $documents = $this->one($x, '//*[@data-step-panel="4"]');
        $this->assertTrue($documents->hasAttribute('data-js-only'));
        $this->assertTrue($documents->hasAttribute('hidden'));
    }

    /**
     * Design 2.1, Phase 1 (brief §3.5, §3.6; D02, D04, D06): every button is Ink, Secondary or Text (the submit
     * is Ink, never gold, and carries no decorative icon); only Back and Next keep their arrows, under the
     * allowed names form-back / form-next; the audience choices have no icons; the notices show their tone's
     * icon (no clock or shield); the contact group's hidden name has no middle dot.
     */
    #[DataProvider('locales')]
    public function test_design_2_1_ink_buttons_allowed_arrows_and_no_decorative_icons(string $locale): void
    {
        $html = $this->home($locale);
        $x = $this->dom($html);
        $wizard = $this->one($x, '//div[@data-wizard]');
        $markup = $wizard->ownerDocument->saveHTML($wizard);

        $submit = $this->one($x, '//button[@data-wizard-submit]');
        $this->assertStringContainsString('btn--ink', $submit->getAttribute('class'), 'the submit is Ink');
        $this->assertSame(0, $x->query('.//*[contains(concat(" ", @class, " "), " btn__icon ")]', $submit)->length, 'no icon on the submit');
        $this->assertSame(1, $x->query('.//*[contains(concat(" ", @class, " "), " btn__label ")][normalize-space(.)="'.content()->page('P01', $locale)['content']['form_home_variant']['submit'].'"]', $submit)->length);
        $this->assertStringNotContainsString('btn--signature', $markup, 'no gold button in the wizard');
        $this->assertStringNotContainsString('btn--gold', $markup, 'no gold button in the wizard');

        $this->assertSame(1, $x->query('.//*[@data-icon="form-back"]', $this->one($x, '//button[@data-wizard-back]'))->length, 'Back keeps its allowed arrow');
        $this->assertSame(1, $x->query('.//*[@data-icon="form-next"]', $this->one($x, '//button[@data-wizard-next]'))->length, 'Next keeps its allowed arrow');

        $this->assertStringNotContainsString('wizard__choice-icon', $markup, 'audience choices without icons');
        preg_match_all('/data-icon="([^"]+)"/', $markup, $m);
        // (carousel-prev / carousel-next: the month buttons of the date picker, <x-d2.ui.date>, D36)
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), ['form-back', 'form-next', 'info', 'warning', 'error', 'check', 'close', 'chevron-down', 'calendar', 'carousel-prev', 'carousel-next', 'phone', 'mail', 'whatsapp'])), "{$locale}: functional icons only");

        $legend = $this->one($x, '//fieldset[@id="wz-contact"]/legend');
        $this->assertStringNotContainsString('·', $legend->textContent, 'no middle dot in the contact group name');
        $variant = content()->page('P01', $locale)['content']['form_home_variant'];
        $this->assertStringContainsString($variant['labels']['phone'], $legend->textContent);
        $this->assertStringContainsString($variant['labels']['email'], $legend->textContent);
    }

    /**
     * Design 2.1, Phase 1 (brief §3.4, §3.5): the wizard and its cards are flat (white, 1px line, radius tokens, no
     * shadow, no monospace or tracking on the reference), and the audience cards of one row start their content at
     * the top: the radio row centres a single line, which inside equal-height cards dropped the rings and titles of
     * the cards with shorter hints below the others'.
     */
    public function test_design_2_1_wizard_styles_are_flat_and_the_audience_cards_line_up(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/components/wizard.css'));
        $this->assertMatchesRegularExpression('/\.wizard__choice\.radio \{[^}]*align-content: start;/', $css, 'audience cards: content from the top');
        $this->assertDoesNotMatchRegularExpression('/box-shadow:(?!\s*none\b)/', $css, 'no shadow');
        $this->assertDoesNotMatchRegularExpression('/var\(--(font-mono|font-doc|ls-caps|fs-meta|fw-bold|fw-h3|fw-ui|fw-meta|r-lg|r-md|surface|surface-sunken|success-tint|shadow-\d)\)|letter-spacing:\s*0\.\d/', $css, 'Design 2.1 tokens only, no tracking');
    }

    public function test_after_a_no_js_round_trip_the_controller_loads_at_once_on_the_step_it_opens(): void
    {
        $payload = $this->plainSubmission($this->dom($this->home('en')), ['name' => 'A. Example', 'phone' => '+971 50 000 0000', 'email' => '', 'summary' => '']);
        $this->post('/en/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303);

        $x = $this->dom($this->home('en'));
        $root = $this->one($x, '//div[@data-wizard]');
        // summary (step 2) and enquiry_consent (step 5): the wizard opens on the first of them.
        $this->assertSame('eager', $root->getAttribute('data-wizard-mount'));
        $this->assertSame('2', $root->getAttribute('data-step'));
        $this->assertFalse($root->hasAttribute('data-wizard-offer-draft'), 'never a restore prompt over a round trip');
        $this->assertFalse($this->one($x, '//*[@data-step-panel="2"]')->hasAttribute('data-wizard-later'));
        $this->assertTrue($this->one($x, '//*[@data-step-panel="1"]')->hasAttribute('data-wizard-later'));
        $this->assertFalse($this->one($x, '//button[@data-wizard-back]')->hasAttribute('hidden'));

        // A delivery notice opens the review: Submit shows at once, Next does not.
        $y = $this->dom($this->withSession(['enquiry_status' => 'failed'])->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->assertOk()->getContent());
        $this->assertSame('5', $this->one($y, '//div[@data-wizard]')->getAttribute('data-step'));
        $this->assertFalse($this->one($y, '//button[@data-wizard-submit]')->hasAttribute('data-wizard-later'));
        $this->assertTrue($this->one($y, '//button[@data-wizard-next]')->hasAttribute('hidden'));
        $this->assertFalse($this->one($y, '//*[@data-step-panel="5"]')->hasAttribute('data-wizard-later'));
    }

    public function test_the_context_lines_are_server_rendered_so_nothing_moves_when_the_controller_mounts(): void
    {
        $x = $this->dom($this->home('en', '?consultant=team-ahmed&tool=gratuity'));
        $context = $this->one($x, '//*[@data-wizard-context]');
        $this->assertFalse($context->hasAttribute('hidden'));
        $lines = [];
        foreach ($x->query('.//*[@data-wizard-context-list]/li', $context) as $li) {
            $lines[] = trim($li->textContent);
        }
        $this->assertSame([
            d2p('wizard.context.consultant', ['value' => content()->record('team_members', 'team-ahmed')['name']]),
            d2p('wizard.context.tool', ['value' => d2p('wizard.tool.gratuity')]),
        ], $lines);

        $this->assertTrue($this->one($this->dom($this->home('en')), '//*[@data-wizard-context]')->hasAttribute('hidden'));
    }

    // ------------------------------------------------------------------ the draft on this device

    #[DataProvider('locales')]
    public function test_the_visitor_sees_that_the_draft_stays_on_this_device_and_can_switch_it_off(string $locale): void
    {
        $x = $this->dom($this->home($locale));

        // "Keep a draft on this device": ticked, never posted, JavaScript only, with the lifetime in words.
        $box = $this->one($x, '//input[@data-wizard-autosave]');
        $this->assertSame('checkbox', $box->getAttribute('type'));
        $this->assertSame('wz-local', $box->getAttribute('form'));
        $this->assertFalse($box->hasAttribute('name'));
        $this->assertTrue($box->hasAttribute('checked'));
        $this->assertSame(1, $x->query('ancestor::*[@data-js-only]', $box)->length);
        $this->assertStringContainsString(d2p('wizard.draft.keep', [], $locale), $this->one($x, '//label[@for="wz-autosave"][contains(@class, "check__label")]')->textContent);
        $hint = $this->one($x, '//*[@id="wz-autosave-hint"]');
        $this->assertSame('wz-autosave-hint', $box->getAttribute('aria-describedby'));
        $this->assertSame('7', trim($this->one($x, '//*[@id="wz-autosave-hint"]/bdi')->textContent));
        $this->assertSame(
            trim(str_replace('__N__', '7', trans_choice('proposed.d2.wizard.draft.keep_hint', 7, ['count' => '__N__'], $locale))),
            trim(preg_replace('/\s+/u', ' ', $hint->textContent)),
        );

        // The restore prompt and the toast say where the draft is kept; the prompt deletes it.
        $restore = $this->one($x, '//*[@data-wizard-restore]');
        $this->assertStringContainsString(d2p('wizard.draft.found', [], $locale), $restore->textContent);
        $this->assertStringContainsString(d2p('wizard.draft.discard', [], $locale), $this->one($x, '//*[@data-wizard-restore-discard]')->textContent);
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(d2p('wizard.draft.saved', [], $locale), $data['text']['draftSaved']);
        $this->assertSame(d2p('wizard.draft.off', [], $locale), $data['text']['draftOff']);
    }

    public function test_the_draft_lifetime_has_every_plural_form(): void
    {
        $forms = fn (string $locale) => count(explode('|', (string) (require lang_path($locale.'/proposed-d2/wizard.php'))['draft']['keep_hint']));
        $this->assertSame(2, $forms('en'));
        $this->assertSame(3, $forms('ru'));
        $this->assertSame(6, $forms('ar'));
    }

    // ------------------------------------------------------------------ Design 2.1, Phase 2: D37, D38, D39, §5.2

    /** The posted and wizard-only controls of the wizard, in document order. */
    private function fieldOrder(DOMXPath $x): array
    {
        $names = [];
        foreach ($x->query('//form[@data-wizard-form]//*[@data-wizard-field]') as $control) {
            $names[] = $control->getAttribute('data-wizard-field');
        }

        return $names;
    }

    /**
     * D38 (the Contact file's form layout): name and contact details first, then the optional language and matter
     * type, the summary, the deadline details, the optional attachment, the visible notices, consent and submit.
     * Five steps: You, Your matter, Deadline, Documents, Review.
     */
    #[DataProvider('locales')]
    public function test_d38_the_fields_follow_the_contact_docx_order(string $locale): void
    {
        foreach ([$this->home($locale), $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/contact/")->assertOk()->getContent()] as $html) {
            $x = $this->dom($html);
            $this->assertSame(['name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date', 'deadline_details', 'enquiry_consent'], $this->fieldOrder($x), $locale);

            $panels = [];
            foreach ($x->query('//*[@data-step-panel]') as $panel) {
                $panels[] = $panel->getAttribute('data-step-panel').':'.preg_replace('/^.*wizard__step--(\S+).*$/', '$1', $panel->getAttribute('class'));
            }
            $this->assertSame(['1:you', '2:matter', '3:deadline', '4:documents', '5:review'], $panels);
            foreach (['name' => 1, 'phone' => 1, 'email' => 1, 'preferred_language' => 1, 'matter_type' => 2, 'summary' => 2, 'deadline_date' => 3, 'deadline_details' => 3, 'enquiry_consent' => 5] as $field => $n) {
                $this->assertSame(1, $x->query('//*[@data-step-panel="'.$n.'"]//*[@data-wizard-field="'.$field.'"]')->length, "{$field} on step {$n}");
            }
            // The notices come before the consent, the consent before the submit button.
            $this->assertSame(1, $x->query('//*[contains(@class, "wizard__notes")]/following::input[@name="enquiry_consent"]')->length);
            $this->assertSame(1, $x->query('//input[@name="enquiry_consent"]/following::button[@data-wizard-submit]')->length);
            // Labels above inputs (brief §5.2): every visible field's label comes before its control.
            foreach (['name', 'phone', 'email', 'summary'] as $field) {
                $control = $this->one($x, '//*[@data-wizard-field="'.$field.'"]');
                $this->assertSame(1, $x->query('preceding::label[@for="'.$control->getAttribute('id').'"]', $control)->length, "{$field}: label above");
            }
        }
    }

    /** D37: one progress indicator, a step list on wide screens and "Step 1 of 5: You" on phones (no chips, no bar). */
    #[DataProvider('locales')]
    public function test_d37_one_progress_indicator(string $locale): void
    {
        $x = $this->dom($this->home($locale));
        $steps = $this->one($x, '//*[@data-wizard-steps]');
        $this->assertSame(0, $x->query('.//progress | .//*[contains(@class, "step-chips")] | .//*[contains(@class, "progress")]', $steps)->length, 'no chips and no bar');
        $this->assertSame(5, $x->query('.//ol[contains(@class, "step-list__items")]/li', $steps)->length);
        $this->assertSame(1, $x->query('.//li[@aria-current="step"]', $steps)->length);
        $now = $this->one($x, '//*[@data-wizard-steps]//p[contains(@class, "step-list__now")]');
        $this->assertSame('true', $now->getAttribute('aria-hidden'));
        $this->assertSame(d2p('controls.step_of', ['current' => 1, 'total' => 5], $locale).': '.d2p('wizard.steps.you', [], $locale), trim(preg_replace('/\s+/u', ' ', $now->textContent)));
        // One template per step for the controller; step 3's list marks steps 1 and 2 as done buttons.
        $this->assertSame(5, $x->query('//template[@data-wizard-steps-for]')->length);
        $third = $this->one($x, '//template[@data-wizard-steps-for="3"]');
        $markup = $third->ownerDocument->saveHTML($third);
        $this->assertSame(2, substr_count($markup, 'data-step="'), 'finished steps are buttons');
        // The step names are proposed copy: marked on the list and on the phone line (brief 8.7).
        $this->assertMatchesRegularExpression('/'.preg_quote(d2p('controls.step_of', ['current' => 3, 'total' => 5], $locale), '/').'<\/span>: <span\s+data-copy-status="proposed"\s*>'.preg_quote(e(d2p('wizard.steps.deadline', [], $locale)), '/').'/', $markup);

        $css = (string) file_get_contents(resource_path('css/d2/components/progress.css'));
        $this->assertMatchesRegularExpression('/@media \(width < 768px\) \{\s*\.step-list__items \{\s*display: none;\s*\}\s*\.step-list__now \{\s*display: block;/', $css, 'phones: the one line only');
        $this->assertStringNotContainsString('step-chips', $css);
    }

    /** D39: "You are contacting us as" and "Keep a draft on this device" (and the local draft) only while pending copy shows. */
    public function test_d39_the_pending_fields_render_only_while_pending_copy_is_shown(): void
    {
        $x = $this->dom($this->home('en'));
        $this->assertSame(3, $x->query('//input[@data-wizard-audience]')->length, 'pending shown (not production)');
        $this->one($x, '//input[@data-wizard-autosave]');
        $this->assertSame(2, $x->query('//*[@data-pending]')->length);

        config(['design.d2.show_pending' => false]);
        $x = $this->dom($this->home('en', '?audience=business&service=S06'));
        $root = $this->one($x, '//div[@data-wizard]');
        $this->assertSame(0, $x->query('//input[@data-wizard-audience] | //*[@id="wz-audience"] | //*[@data-review-row="audience"]')->length, 'no audience choice');
        $this->assertSame(0, $x->query('//input[@data-wizard-autosave] | //*[@data-wizard-restore] | //*[@data-pending]')->length, 'no draft choice and no restore prompt');
        $this->assertFalse($root->hasAttribute('data-wizard-offer-draft'));
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($data['draft'], 'the controller keeps no local draft');
        $this->assertSame([], $data['labels']['audience']);
        $this->assertStringNotContainsString(d2p('wizard.context.audience', ['value' => d2p('wizard.audience.business')]), $this->one($x, '//textarea[@name="summary"]')->textContent);
        // The rest of the form is unchanged.
        $this->assertSame(['name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date', 'deadline_details', 'enquiry_consent'], $this->fieldOrder($x));
    }

    /**
     * §5.2: consent is unchecked and required and only the words "Privacy Policy" are a link, in each page's own
     * sentence; required are the name, the summary and one contact method (the asterisks and the browser checks).
     */
    #[DataProvider('locales')]
    public function test_consent_links_only_the_privacy_policy_words_in_each_pages_sentence(string $locale): void
    {
        $pages = [
            'home' => [$this->home($locale), content()->page('P01', $locale)['content']['form_home_variant']['consent']],
            'contact' => [$this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/contact/")->assertOk()->getContent(), ui('form.consent.label', $locale)],
        ];
        foreach ($pages as $page => [$html, $sentence]) {
            $x = $this->dom($html);
            $consent = $this->one($x, '//input[@name="enquiry_consent"]');
            $this->assertFalse($consent->hasAttribute('checked'), $page);
            $this->assertSame('true', $consent->getAttribute('aria-required'), $page);
            $label = $this->one($x, '//label[@for="'.$consent->getAttribute('id').'"]');
            $this->assertSame($sentence, trim(str_replace('*', '', preg_replace('/\s+/u', ' ', $label->textContent))), "{$locale} {$page}: the page's own sentence");
            $links = $x->query('.//a', $label);
            $this->assertSame(1, $links->length, $page);
            $this->assertSame(ui('form.consent.link', $locale), trim($links->item(0)->textContent), "{$locale} {$page}: only the words Privacy Policy");
            $this->assertSame(\App\Support\SiteUrl::page('L01'), $links->item(0)->getAttribute('href'));
            // required: the name and the summary carry the asterisk; phone and e-mail are a group (one is enough)
            foreach (['name', 'summary'] as $field) {
                $this->assertSame(1, $x->query('//label[@for="'.$this->one($x, '//*[@name="'.$field.'"]')->getAttribute('id').'"]/span[contains(@class, "field__req")]')->length, "{$page}: {$field} required");
            }
            foreach (['phone', 'email'] as $field) {
                $this->assertSame(0, $x->query('//label[@for="'.$this->one($x, '//*[@name="'.$field.'"]')->getAttribute('id').'"]/span[contains(@class, "field__req")]')->length, "{$page}: {$field} alone is not required");
            }
        }
    }

    /** Each page's own Word file: P07 prints the Contact file's labels, help, options and notices. */
    #[DataProvider('locales')]
    public function test_the_contact_page_form_uses_the_contact_files_copy(string $locale): void
    {
        $x = $this->dom($this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/contact/")->assertOk()->getContent());
        $form = $this->one($x, '//form[@data-wizard-form]');
        foreach (['name.label', 'name.help', 'phone.label', 'phone.help', 'email.label', 'email.help', 'contact_group.help', 'preferred_language.label', 'matter_type.label', 'summary.label', 'summary.help', 'deadline_date.label', 'deadline_date.help', 'deadline_details.label', 'notice.sensitive', 'notice.engagement', 'submit'] as $key) {
            $this->assertStringContainsString(ui('form.'.$key, $locale), $form->textContent, "{$locale}: {$key}");
        }
        $matter = [];
        foreach ($x->query('.//select[@name="matter_type"]/option', $form) as $option) {
            $matter[] = $option->getAttribute('value');
        }
        $this->assertSame(['', ...StoreEnquiryRequest::matterTypes()], $matter);
        $this->assertSame(0, $x->query('//*[@data-wizard-after]')->length, 'both notices before the consent');
        $this->assertSame('consultation-title', $form->getAttribute('aria-labelledby'));
    }

    /** The homepage file puts its engagement notice after the submit button (the Contact file before the consent). */
    public function test_the_home_engagement_notice_follows_the_submit_button(): void
    {
        $x = $this->dom($this->home('en'));
        $after = $this->one($x, '//form[@data-wizard-form]//p[@data-wizard-after]');
        $this->assertSame(content()->page('P01', 'en')['content']['form_home_variant']['engagement_notice'], trim($after->textContent));
        $this->assertSame(1, $x->query('preceding::button[@data-wizard-submit]', $after)->length);
        $this->assertSame(1, $this->one($x, '//*[contains(@class, "wizard__notes")]')->getElementsByTagName('p')->length, 'only the sensitive-documents notice before the consent');
        $css = (string) file_get_contents(resource_path('css/d2/components/wizard.css'));
        $this->assertStringContainsString(':root.js .wizard:not(.is-plain, [data-step="5"]) .wizard__after', $css, 'with JavaScript on the review step only');
    }

    /** No-JS field errors on the home read in the homepage file's own wording (the endpoint sends the Contact file's). */
    public function test_no_js_errors_on_the_home_use_the_homepage_files_messages(): void
    {
        $payload = $this->plainSubmission($this->dom($this->home('ru')), ['name' => '', 'phone' => '+971 ', 'email' => '', 'summary' => '']);
        $this->post('/ru/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303);

        $x = $this->dom($this->home('ru'));
        $states = content()->page('P01', 'ru')['content']['form_home_variant']['states'];
        $items = [];
        foreach ($x->query('//*[@data-wizard-notice="errors"]//li') as $li) {
            $items[] = trim($li->textContent);
        }
        // The homepage file's order: missing name, invalid contact, missing summary, missing consent.
        $this->assertSame([$states['missing_name'], $states['invalid_contact'], $states['missing_summary'], $states['missing_consent']], $items);
        $this->assertSame($states['missing_name'], trim($this->one($x, '//*[@id="wz-name-error"]')->textContent));
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$states['missing_summary']], $data['initial']['errors']['summary']);
        $this->assertSame($states['submitting'], $data['text']['busy']);
    }

    // ------------------------------------------------------------------ the optional document

    /**
     * Brief §5.2 and both Word files: the attachment field may exist only if the backend checks type and size from the
     * content, scans the file and restricts access. The shared backend does not scan (App\Design\D2\Attachment), so
     * Design 2 shows no field and no hint, even with SUMMIT_ATTACHMENTS_ENABLED on, and the form works without a file.
     */
    public function test_without_a_scanning_backend_design_2_never_shows_the_attachment(): void
    {
        foreach ([false, true] as $enabled) {
            config(['summit.features.attachments_enabled' => $enabled]);
            $this->assertFalse(\App\Design\D2\Attachment::enabled());
            foreach (['/en/', '/en/contact/'] as $url) {
                $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($url)->assertOk()->getContent();
                $x = $this->dom($html);
                $this->assertFalse($this->one($x, '//form[@data-wizard-form]')->hasAttribute('enctype'), $url);
                $this->assertSame(0, $x->query('//*[@data-upload] | //input[@type="file"] | //*[@data-review="attachment"]')->length, $url);
                $this->assertStringNotContainsString(ui('form.attachment.label'), $html, $url);
                $this->assertStringNotContainsString(ui('form.attachment.help'), $html, $url);
                $this->assertStringNotContainsString(content()->page('P01', 'en')['content']['form_home_variant']['attachment_hint'], $html, $url);
                // The Documents step is the checklist only, so it needs JavaScript.
                $this->assertTrue($this->one($x, '//*[@data-step-panel="4"]')->hasAttribute('data-js-only'), $url);
            }
        }
    }

    // (The dormant path, for when the backend scans: design.d2.attachment_scanned + attachments_enabled.)

    #[DataProvider('locales')]
    public function test_with_a_scanning_backend_the_documents_step_holds_the_dropzone_for_every_visitor(string $locale): void
    {
        config(['summit.features.attachments_enabled' => true, 'design.d2.attachment_scanned' => true]);
        $x = $this->dom($this->home($locale));

        $form = $this->one($x, '//form[@data-wizard-form]');
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));

        // Documents exists without JavaScript too (the one document posts with the plain form) …
        $step = $this->one($x, '//*[@data-step-panel="4"]');
        $this->assertFalse($step->hasAttribute('data-js-only'));
        $this->assertFalse($step->hasAttribute('hidden'));
        $this->assertTrue($step->hasAttribute('data-wizard-later'), 'with JavaScript it waits for its turn');
        // … while the checklist, its notes and the checklist intro stay JavaScript-only.
        $this->assertTrue($this->one($x, '//*[@data-step-panel="4"]//fieldset[@data-wizard-docs]')->hasAttribute('data-js-only'));
        foreach (['none', 'generic'] as $kind) {
            $this->assertTrue($this->one($x, '//*[@data-step-panel="4"]//*[@data-wizard-docs-empty="'.$kind.'"]')->hasAttribute('data-js-only'), $kind);
        }
        $this->assertTrue($this->one($x, '//*[@data-step-panel="4"]//p[contains(@class, "wizard__step-intro")]')->hasAttribute('data-js-only'));

        // The dropzone comes after the checklist, on the wizard's field contract.
        $input = $this->one($x, '//*[@data-step-panel="4"]//input[@type="file"]');
        $this->assertSame('attachment', $input->getAttribute('name'));
        $this->assertSame('wz-attachment', $input->getAttribute('id'));
        $this->assertFalse($input->hasAttribute('form'));
        $this->one($x, '//*[@data-step-panel="4"]//*[@id="wz-attachment-error"]');
        $docs = $this->one($x, '//*[@data-step-panel="4"]//fieldset[@data-wizard-docs]');
        $this->assertSame(1, $x->query('following::input[@id="wz-attachment"]', $docs)->length, 'after the checklist');

        // The review names the chosen file in the Documents group.
        $this->one($x, '//*[@data-step-panel="5"]//*[@data-review-row="attachment"]//*[@data-review="attachment"]');
        $this->assertStringContainsString(d2p('wizard.review.attachment', [], $locale), $this->one($x, '//*[@data-review-row="attachment"]')->textContent);
    }

    public function test_with_a_scanning_backend_the_plain_form_posts_its_document_to_the_endpoint(): void
    {
        config(['summit.features.attachments_enabled' => true, 'design.d2.attachment_scanned' => true, 'summit.turnstile.enabled' => false]);
        Storage::fake('local');
        $pdf = UploadedFile::fake()->createWithContent('employment-contract.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $payload = $this->plainSubmission($this->dom($this->home('en')), [
            'name' => 'A. Example',
            'email' => 'a.example@example.com',
            'summary' => 'My final settlement looks wrong.',
            'enquiry_consent' => '1',
            'attachment' => $pdf,
        ]);
        $this->assertEqualsCanonicalizing([...self::POSTED, 'attachment'], array_keys($payload));

        $this->post('/en/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303)->assertSessionHas('enquiry_status', 'received');
        $enquiry = Enquiry::sole();
        $this->assertSame('application/pdf', $enquiry->attachment_mime);
        Storage::disk('local')->assertExists((string) $enquiry->attachment_path);
    }

    public function test_with_a_scanning_backend_a_refused_document_is_linked_from_the_error_summary(): void
    {
        config(['summit.features.attachments_enabled' => true, 'design.d2.attachment_scanned' => true, 'summit.turnstile.enabled' => false]);
        Storage::fake('local');
        $payload = $this->plainSubmission($this->dom($this->home('en')), [
            'name' => 'A. Example',
            'email' => 'a.example@example.com',
            'summary' => 'Summary.',
            'enquiry_consent' => '1',
            'attachment' => UploadedFile::fake()->createWithContent('contract.pdf', 'plain text, not a document'),
        ]);
        $this->post('/en/enquiries', $payload, ['Accept' => 'text/html'])->assertStatus(303);
        $this->assertSame(0, Enquiry::count());

        $x = $this->dom($this->home('en'));
        $links = [];
        foreach ($x->query('//*[@data-wizard-notice="errors"]//a') as $a) {
            $links[] = $a->getAttribute('href');
        }
        $this->assertSame(['#wz-attachment'], $links);
        $this->assertSame(ui('form.attachment.error_type'), trim($this->one($x, '//*[@id="wz-attachment-error"]')->textContent));
        $this->assertSame('true', $this->one($x, '//input[@id="wz-attachment"]')->getAttribute('aria-invalid'));
        // With JavaScript the wizard opens on Documents, where the message is.
        $this->assertSame('4', $this->one($x, '//div[@data-wizard]')->getAttribute('data-step'));
    }

    // ------------------------------------------------------------------ copy

    public function test_every_proposed_wizard_key_exists_in_all_three_languages(): void
    {
        $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($values as $key => $value) {
                $out += is_array($value) ? $flatten($value, $prefix.$key.'.') : [$prefix.$key => $value];
            }

            return $out;
        };
        $en = $flatten(require lang_path('en/proposed-d2/wizard.php'));
        foreach (['ar', 'ru'] as $locale) {
            $other = $flatten(require lang_path($locale.'/proposed-d2/wizard.php'));
            $this->assertSame(array_keys($en), array_keys($other), $locale);
            foreach ($other as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "{$locale}: {$key}");
                if (! in_array($key, ['context.audience', 'context.situation', 'context.consultant', 'context.tool'], true)) {
                    $this->assertNotSame($en[$key], $value, "{$locale}: {$key} is still English");
                }
            }
        }
    }
}
