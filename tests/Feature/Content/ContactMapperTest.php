<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\ContactMapper;

final class ContactMapperTest extends MapperTestCase
{
    /** Strings of the shared consultation form component, keyed by the red markers of 07_Contact_Us. */
    private const FORM_KEYS = [
        'form.heading', 'form.intro', 'form.required_note',
        'form.name.label', 'form.name.help', 'form.name.error',
        'form.phone.label', 'form.phone.help', 'form.phone.error',
        'form.email.label', 'form.email.help', 'form.email.error',
        'form.preferred_language.label', 'form.preferred_language.placeholder',
        'form.preferred_language.option.ar', 'form.preferred_language.option.en', 'form.preferred_language.option.ru',
        'form.matter_type.label', 'form.matter_type.placeholder',
        'form.matter_type.option.S01', 'form.matter_type.option.S02', 'form.matter_type.option.S03',
        'form.matter_type.option.S04', 'form.matter_type.option.S05', 'form.matter_type.option.S06',
        'form.matter_type.option.S07', 'form.matter_type.option.S08', 'form.matter_type.option.S09',
        'form.matter_type.option.S10', 'form.matter_type.option.S11',
        'form.matter_type.option.other', 'form.matter_type.option.unsure',
        'form.summary.label', 'form.summary.help', 'form.summary.error',
        'form.deadline_date.label', 'form.deadline_date.help', 'form.deadline_date.error',
        'form.deadline_details.label', 'form.deadline_details.help',
        'form.attachment.label', 'form.attachment.help', 'form.attachment.remove',
        'form.contact_group.help', 'form.notice.sensitive', 'form.notice.engagement',
        'form.consent.label', 'form.consent.link',
        'form.submit',
        'form.contact_group.error', 'form.consent.error',
        'form.attachment.error_type', 'form.attachment.error_size', 'form.attachment.error_count', 'form.attachment.error_rejected',
        'form.state.summary', 'form.state.busy', 'form.state.success', 'form.state.failure',
        'form.state.uncertain', 'form.state.rate_limited', 'form.state.new_enquiry',
    ];

    private const LOCALES = ['en', 'ar', 'ru'];

    private function imported(): array
    {
        return $this->import([new ContactMapper]);
    }

    public function test_every_public_block_is_mapped_in_all_languages(): void
    {
        $this->assertFullCoverage($this->imported());
    }

    public function test_no_designer_note_reaches_public_output(): void
    {
        $this->assertNoDesignerNotes($this->imported());
    }

    public function test_languages_have_identical_structure(): void
    {
        $this->assertNoParityIssues($this->imported());
    }

    public function test_the_form_produces_exactly_the_component_keys_in_every_language(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $expected = self::FORM_KEYS;
        $actual = array_keys($ui);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
        foreach (self::FORM_KEYS as $key) {
            foreach (self::LOCALES as $locale) {
                $this->assertIsString($ui[$key][$locale] ?? null, "{$key} is missing in {$locale}");
                $this->assertNotSame('', trim($ui[$key][$locale]), "{$key} is empty in {$locale}");
            }
        }
    }

    public function test_form_strings_are_keyed_by_the_red_markers(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $this->assertSame('Debts and Cheques', $ui['form.matter_type.option.S01']['en']);
        $this->assertSame("Sending request\u{2026}", $ui['form.state.busy']['en']);
        $this->assertSame('Consultation Request Details', $ui['form.heading']['en']);
        $this->assertSame('Name', $ui['form.name.label']['en']);
        $this->assertSame('Please enter your name.', $ui['form.name.error']['en']);
        $this->assertSame('Choose a language', $ui['form.preferred_language.placeholder']['en']);
        $this->assertSame('Choose a topic if you know it', $ui['form.matter_type.placeholder']['en']);
        $this->assertSame('Business Services', $ui['form.matter_type.option.S11']['en']);
        $this->assertSame('Not sure which category applies', $ui['form.matter_type.option.unsure']['en']);
        $this->assertSame('Remove Document', $ui['form.attachment.remove']['en']);
        $this->assertSame('Submit Consultation Request', $ui['form.submit']['en']);
        $this->assertSame('Send a New Enquiry', $ui['form.state.new_enquiry']['en']);
        $this->assertSame('الديون والشيكات', $ui['form.matter_type.option.S01']['ar']);
        $this->assertSame('العربية', $ui['form.preferred_language.option.ar']['ar']);
        $this->assertSame("جارٍ إرسال الطلب\u{2026}", $ui['form.state.busy']['ar']);
        $this->assertSame('Долги и чеки', $ui['form.matter_type.option.S01']['ru']);
        $this->assertSame('Русский', $ui['form.preferred_language.option.ru']['ru']);
        $this->assertSame("Отправка запроса\u{2026}", $ui['form.state.busy']['ru']);
    }

    public function test_consent_link_phrase_occurs_inside_the_label_in_every_language(): void
    {
        $result = $this->imported();
        $ui = $result['datasets']['ui_strings'];
        foreach (self::LOCALES as $locale) {
            // The form component links the first occurrence of form.consent.link inside the label
            // and renders no policy link at all when it is not found.
            $this->assertNotFalse(
                mb_strpos($ui['form.consent.label'][$locale], $ui['form.consent.link'][$locale]),
                "form.consent.link is not part of form.consent.label in {$locale}",
            );
        }
        $this->assertSame('Privacy Policy', $ui['form.consent.link']['en']);
        $this->assertSame('سياسة الخصوصية', $ui['form.consent.link']['ar']);
        // RU red note: link the inflected phrase already in the sentence, never the nominative
        // P07-L007 label "Политика конфиденциальности" (the footer wording).
        $this->assertSame('Политикой конфиденциальности', $ui['form.consent.link']['ru']);
        $notes = array_filter($result['report']->entries('note'), fn ($e) => $e['subject'] === 'form.consent.link');
        $this->assertSame(['ru'], array_values(array_column($notes, 'locale')));
    }

    public function test_arabic_and_russian_messages_follow_their_markers(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $expected = [
            'form.phone.error' => ['يرجى إدخال رقم هاتف صالح مع مفتاح الدولة.', 'Введите корректный номер телефона с кодом страны.'],
            'form.contact_group.error' => ['يرجى إدخال رقم هاتف أو بريد إلكتروني صالح للتواصل.', 'Укажите корректный номер телефона или адрес электронной почты для связи.'],
            'form.contact_group.help' => ['يكفي إدخال رقم هاتف أو بريد إلكتروني صالح، ويمكنك إضافة الاثنين إذا رغبت.', 'Достаточно корректного номера телефона или адреса электронной почты. При желании можно указать оба.'],
            'form.phone.help' => ['أضف مفتاح الدولة، وأدخل رقمًا يمكن التواصل معك عليه.', 'Укажите код страны и номер, по которому с вами можно связаться.'],
            'form.deadline_date.help' => ['اذكر أقرب موعد للجلسة أو الرد أو الطعن، إن وجد.', 'Укажите ближайшую дату заседания или срок ответа либо обжалования, если они установлены.'],
            'form.deadline_details.help' => ['اذكر الجهة المعنية ونوع الموعد، ووضّح إذا كان قد انقضى بالفعل.', 'Укажите соответствующий орган и поясните, к чему относится дата или срок; сообщите, если они уже прошли.'],
            'form.state.summary' => ['يرجى مراجعة البيانات المشار إليها قبل إرسال الطلب.', 'Проверьте отмеченные данные перед отправкой запроса.'],
            'form.state.failure' => ['تعذّر إرسال الطلب. يرجى المحاولة مرة أخرى أو التواصل معنا مباشرة.', 'Не удалось отправить запрос. Попробуйте ещё раз или свяжитесь с нами напрямую.'],
            'form.state.uncertain' => ['لم نتمكن من تأكيد استلام الطلب. يمكنك التواصل معنا مباشرة للتحقق قبل إعادة الإرسال.', 'Мы не смогли подтвердить получение запроса. Перед повторной отправкой вы можете связаться с нами напрямую для проверки.'],
            'form.state.rate_limited' => ['تعذّر إرسال طلب آخر الآن. يرجى المحاولة لاحقًا أو التواصل معنا مباشرة.', 'Сейчас нельзя отправить ещё один запрос. Попробуйте позже или свяжитесь с нами напрямую.'],
            'form.state.new_enquiry' => ['إرسال استفسار جديد', 'Отправить новый запрос'],
            'form.matter_type.option.S11' => ['خدمات الأعمال', 'Услуги для бизнеса'],
            'form.matter_type.option.other' => ['مسألة أخرى', 'Другой вопрос'],
            'form.matter_type.option.unsure' => ['لست متأكدًا من التصنيف', 'Не знаю, какую категорию выбрать'],
            'form.preferred_language.placeholder' => ['اختر اللغة', 'Выберите язык'],
            'form.matter_type.placeholder' => ['اختر الموضوع إن كنت تعرفه', 'Выберите тему, если она вам известна'],
        ];
        foreach ($expected as $key => [$ar, $ru]) {
            $this->assertSame($ar, $ui[$key]['ar'], "{$key} (ar)");
            $this->assertSame($ru, $ui[$key]['ru'], "{$key} (ru)");
        }
    }

    public function test_contact_page_keeps_every_section_in_each_language(): void
    {
        $d = $this->imported()['datasets'];
        $page = $d['pages']['P07'];
        $this->assertSame('Contact Summit Legal Consultancy', $page['h1']['en']);
        $this->assertSame('تواصل مع سَمِت للاستشارات القانونية', $page['h1']['ar']);
        $this->assertSame('Свяжитесь с Summit Legal Consultancy', $page['h1']['ru']);
        $this->assertSame('Contact Us | Summit Legal Consultancy in the UAE', $page['seo_title']['en']);
        foreach (self::LOCALES as $locale) {
            $content = $page['content'][$locale];
            $this->assertSame('P01', $page['breadcrumb'][$locale][0]['target'], $locale);
            $this->assertNull($page['breadcrumb'][$locale][1]['target'], $locale);
            $this->assertCount(2, $content['paragraphs'], $locale);
            $this->assertSame('consultation-form', $content['hero_cta_target'], $locale);
            $this->assertSame('+971 52 466 6191', $content['details']['phone_display'], $locale);
            $this->assertSame('info@summitlegaluae.com', $content['details']['email'], $locale);
            $this->assertCount(3, $content['after']['steps'], $locale);
            $this->assertSame($d['ui_strings']['form.heading'][$locale], $content['form_section']['heading'], $locale);
            $this->assertCount(5, $content['helps']['items'], $locale);
            $this->assertCount(5, $content['faq']['items'], $locale);
            $this->assertSame(['P05', 'P06', 'P02'], array_column($content['links'], 'target'), $locale);
            $this->assertSame('P08', $content['final']['legal_link_target'], $locale);
        }
        $this->assertSame('1. We Understand Your Enquiry', $page['content']['en']['after']['steps'][0]['title']);
        $this->assertSame('Legal Notice and Scope of Services', $page['content']['en']['final']['legal_link_label']);
    }

    public function test_links_and_whatsapp_controls_agree_with_the_designer_notes(): void
    {
        $result = $this->imported();
        $this->assertSame([], $result['report']->entries('source-conflict'));
        $draft = $result['datasets']['pages']['P07']['whatsapp_draft'];
        $this->assertSame('Hello Summit Legal Consultancy, I would like to request a legal consultation and need help with my matter. Please let me know the steps to book a consultation. Thank you.', $draft['en']);
        foreach (self::LOCALES as $locale) {
            $this->assertNotSame('', $draft[$locale], $locale);
        }
    }
}
