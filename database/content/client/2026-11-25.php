<?php

/*
|--------------------------------------------------------------------------
| Client-approved content: "Final Website Changes Required" (2026-11-25)
|--------------------------------------------------------------------------
| The client's PDF "Summit Final Website.pdf" (developer implementation brief, final revision), transcribed in
| docs/briefs/client-final-changes-2026-11-25.md. Its texts are client-approved copy: App\Content\Cms\ClientLayer applies
| this file on top of the DOCX import (database/content/*.json) on every build and marks what it changes
| copy_source client_approved. `php artisan summit:import` never writes here, and `php artisan db:seed` on deploy
| brings these changes into the CMS tables. Each entry names the PDF item it implements; the operations (set, remove,
| status, create, ui, setting) are described in App\Content\Cms\ClientLayer. Settings stored on a server change
| through the settings migration of the same item (database/settings).
*/

return [
    'source' => 'Summit Final Website.pdf, "Final Website Changes Required" (2026-11-25)',

    'changes' => [
        // PDF item 4: the Russian homepage hero (headline and supporting text; the second paragraph stays).
        ['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Понятная юридическая поддержка. Практичные решения.']],
        ['item' => 4, 'set' => 'pages.P01.content.hero.lead', 'value' => ['ru' => 'Понятная юридическая поддержка для частных лиц, компаний, инвесторов и резидентов — основанная на точном понимании фактов, документов и юридических процедур в Объединённых Арабских Эмиратах.']],
        // PDF item 5 and owner decision 4: the phone number is shown exactly as +971524666191 everywhere (no spaces); the
        // tel: link stays tel:+971524666191. Every text that shows it (contact links, CTA rows, the legal pages' links,
        // the site-map labels) and the contact setting's default; the stored setting: database/settings migration.
        ['item' => 5, 'replace' => '+971 52 466 6191', 'with' => '+971524666191'],
        // PDF items 11 and 12: the note on external links near the beginning of the official sources of the Legal
        // Resources page, Arabic and Russian only (the English page has none: the PDF gives none). A new interface string
        // (the DOCX files have none), editable in the admin's Interface Text.
        ['item' => 11, 'ui' => 'resources.external_note', 'new' => true, 'value' => [
            'ar' => 'ملاحظة حول الروابط الخارجية: تتضمن هذه الصفحة روابط إلى مصادر رسمية وجهات حكومية أو تنظيمية في دولة الإمارات. تم اختيار الروابط العربية الرسمية حيثما كانت متاحة. وفي بعض الحالات، قد تفتح بعض المصادر الرسمية باللغة الإنجليزية إذا لم تكن النسخة العربية متاحة أو إذا كانت الجهة تنشر الدليل المعني باللغة الإنجليزية فقط.',
            'ru' => 'Примечание о внешних ссылках: На этой странице размещены ссылки на официальные источники, государственные и регулирующие органы ОАЭ. Русскоязычные версии таких официальных материалов обычно недоступны, поэтому некоторые ссылки могут открываться на английском или арабском языке. Мы используем только официальные источники, когда они доступны, и не заменяем их неофициальными блогами или сторонними материалами.',
        ]],
        // PDF item 6: one standard WhatsApp message, always in English, on every page in every language (the WhatsApp
        // setting's default; the stored setting: the settings migration of item 6).
        ['item' => 6, 'setting' => 'whatsapp.draft', 'value' => [
            'en' => 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.',
            'ar' => 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.',
            'ru' => 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.',
        ]],
        // PDF item 8: the public label of the Case Studies page, "Case Studies" / «نماذج من ملفات قانونية» / «Юридические
        // кейсы», in the navigation, the footer, the homepage, the page title, the breadcrumb and the SEO title (English
        // and the Arabic navigation already carried it). Where a title or a button named the old Russian label, the label
        // is replaced and the rest kept ("… в ОАЭ", «Все …»); the client is asked to confirm those.
        ['item' => 8, 'ui' => 'nav.case_studies', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'ui' => 'footer.case_studies', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'ui' => 'cta.view_all_case_studies', 'value' => ['ru' => 'Все юридические кейсы']],
        ['item' => 8, 'set' => 'pages.P01.content.cases.heading', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'set' => 'pages.P04.h1', 'value' => ['ru' => 'Юридические кейсы в ОАЭ']],
        ['item' => 8, 'set' => 'pages.P04.seo_title', 'value' => [
            'ar' => 'نماذج من ملفات قانونية في الإمارات | سَمِت للاستشارات القانونية',
            'ru' => 'Юридические кейсы в ОАЭ | Summit Legal Consultancy',
        ]],
        ['item' => 8, 'set' => 'pages.P04.breadcrumb.1.label', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'set' => 'pages.P04.registry_label', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'set' => 'services.S02.sections.related.links.2.label', 'value' => ['ru' => 'Юридические кейсы']],
        ['item' => 8, 'set' => 'services.S05.sections.related.links.2.label', 'value' => ['ru' => 'Юридические кейсы']],
        // PDF item 9 and owner decision 6: the Legal Translation service is cancelled. Its practice area (S10) leaves the
        // site (status draft: kept in the admin, its situations go with it, menus and lists drop it; the old addresses 302
        // to Practice Areas: routes/web.php); the links that led to it go; the service lists that named it no longer do
        // (the client is asked to confirm these two cuts). The guide G08 and the FAQ topic "Legal Translation" stay as
        // information (PDF item 10 keeps every guide); only their links to the service go.
        ['item' => 9, 'status' => 'services.S10', 'value' => 'draft'],
        // The FAQ Centre's "Legal Translation" category (cat-11) and its four questions (faq-67 to faq-70) present the
        // cancelled service as a topic: off the site (drafts, kept in the admin). The guide "How to Check the Accuracy of a
        // Legal Translation" (G08) stays: PDF item 10, "Keep all existing guides and articles".
        ['item' => 9, 'status' => 'faq_categories.cat-11', 'value' => 'draft'],
        ['item' => 9, 'status' => 'faqs.faq-67', 'value' => 'draft'],
        ['item' => 9, 'status' => 'faqs.faq-68', 'value' => 'draft'],
        ['item' => 9, 'status' => 'faqs.faq-69', 'value' => 'draft'],
        ['item' => 9, 'status' => 'faqs.faq-70', 'value' => 'draft'],
        // With faq-67 to faq-70 off the site, the FAQ Centre's numbering stays a sequence: the four Business Services
        // questions (faq-71 to faq-74) are shown as 67 to 70 (their anchors #faq-71 … stay, so links keep working).
        ['item' => 9, 'set' => 'faqs.faq-71.question', 'value' => ['en' => '67. What is the first step in setting up a company?', 'ar' => '67. ما أول خطوة لتأسيس شركة؟', 'ru' => '67. С чего начать создание компании?']],
        ['item' => 9, 'set' => 'faqs.faq-72.question', 'value' => ['en' => '68. Should I choose a free zone or the mainland?', 'ar' => '68. هل أختار منطقة حرة أم البر الرئيسي؟', 'ru' => '68. Что выбрать: свободную зону или основную территорию ОАЭ?']],
        ['item' => 9, 'set' => 'faqs.faq-73.question', 'value' => ['en' => '69. Can the activity be changed after licensing?', 'ar' => '69. هل يمكن تعديل النشاط بعد الترخيص؟', 'ru' => '69. Можно ли изменить вид деятельности после получения лицензии?']],
        ['item' => 9, 'set' => 'faqs.faq-74.question', 'value' => ['en' => '70. What should I prepare before a partner or investor joins?', 'ar' => '70. ماذا أجهز قبل دخول شريك أو مستثمر؟', 'ru' => '70. Что подготовить перед вступлением партнёра или инвестора?']],
        ['item' => 9, 'remove' => 'guides.G08.body.related.items', 'where' => ['target' => 'S10']],
        ['item' => 9, 'remove' => 'faq_categories.cat-11.links', 'where' => ['target' => 'S10']],
        ['item' => 9, 'set' => 'pages.P02.meta_description', 'value' => [
            'en' => 'Explore Summit’s services for debts and cheques, criminal cases, digital assets, contracts, property, employment, tax, family law, compliance and business support.',
            'ar' => 'تعرف على خدمات سَمِت في الديون والشيكات والقضايا الجزائية والعملات الرقمية والعقود والعقارات والعمل والضرائب والأسرة والامتثال وخدمات الأعمال.',
            'ru' => 'Услуги Summit: долги и чеки, уголовные дела, цифровые активы, договоры, недвижимость, трудовые и налоговые вопросы, семейное право, комплаенс и сопровождение бизнеса.',
        ]],
        ['item' => 9, 'set' => 'pages.P01.content.faq.items.7.question', 'value' => [
            'en' => 'Do Your Services Include Tax, Compliance and Business Support?',
            'ar' => 'هل تشمل الخدمات الضرائب والامتثال وخدمات الأعمال؟',
            'ru' => 'Оказываете ли вы услуги по налоговым вопросам, комплаенсу и сопровождению бизнеса?',
        ]],
        // The replacement service, a hidden draft with the approved titles only (no invented content; the Russian title,
        // the content, the SEO texts and the page's address follow from the client): S12, in Legal Translation's place.
        ['item' => 9, 'create' => 'services.S12', 'status' => 'draft', 'value' => [
            'title' => ['en' => 'Preparation and Drafting of Powers of Attorney', 'ar' => 'إعداد وصياغة الوكالات القانونية'],
            'slug' => 'powers-of-attorney',
            'position' => 10,
        ]],
        // PDF item 7 (no contact form): approved copy that names the removed form. Only the form clause is cut; the rest
        // of each text is unchanged (the client is asked to confirm each cut). Kept for now (owner decision 5): the
        // cookie texts' "protect the form" (ui cookies.*, Privacy Policy section 8).
        // the Contact page meta description (og:description)
        ['item' => 7, 'set' => 'pages.P07.meta_description', 'value' => [
            'en' => 'Contact Summit Legal Consultancy by telephone, WhatsApp or email in Arabic, English or Russian with details of your matter.',
            'ar' => 'تواصل مع سَمِت للاستشارات القانونية عبر الهاتف وواتساب والبريد الإلكتروني بالعربية والإنجليزية والروسية مع تفاصيل موضوعك.',
            'ru' => 'Свяжитесь с Summit Legal Consultancy по телефону, WhatsApp или электронной почте на арабском, английском или русском языке, описав свой вопрос.',
        ]],
        // Contact page, "Does an acknowledgement of receipt confirm my appointment?"
        ['item' => 7, 'set' => 'pages.P07.content.faq.items.3.answer', 'value' => [
            'en' => 'An acknowledgement confirms that the request has reached the system. The consultation appointment, acceptance of the engagement and scope of work are determined through communication and agreement.',
            'ar' => 'رسالة الاستلام تؤكد وصول الطلب إلى النظام. أما موعد الاستشارة وقبول التكليف ونطاق العمل فتُحدد بالتواصل والاتفاق.',
            'ru' => 'Сообщение о получении подтверждает поступление запроса в систему. Время консультации, принятие поручения и объём работы определяются при общении и согласовании.',
        ]],
        // Legal Notice, section 3
        ['item' => 7, 'set' => 'pages.P08.content.sections.2.blocks.0.text', 'value' => [
            'en' => 'Reading the website or sending an enquiry or document by email or on WhatsApp does not, by itself, create a professional engagement or mean that responsibility for a matter or its deadlines has been accepted. Work begins once the engagement is accepted and its scope, fees and any necessary documents or authority have been agreed.',
            'ar' => 'قراءة الموقع أو إرسال استفسار أو مستند عبر البريد الإلكتروني أو واتساب لا ينشئ وحده تكليفًا مهنيًا، ولا يعني قبول متابعة ملف أو مواعيده. يبدأ العمل بعد قبول التكليف والاتفاق على نطاقه وأتعابه وما يلزم من مستندات أو صلاحيات.',
            'ru' => 'Ознакомление с сайтом либо отправка запроса или документа по электронной почте или в WhatsApp сами по себе не создают поручения на оказание юридических услуг и не означают принятия дела или контроля его сроков. Работа начинается после принятия поручения и согласования его объёма, вознаграждения, необходимых документов и полномочий.',
        ]],
        // Privacy Policy, introduction
        ['item' => 7, 'set' => 'pages.L01.content.intro.0', 'value' => [
            'en' => 'This policy explains how Summit Legal Consultancy handles data relating to its website and contact requests, including email and WhatsApp, and what you may request in relation to your data. Matters accepted for work are also subject to the requirements of the engagement and the associated professional and legal obligations.',
            'ar' => 'توضح هذه السياسة كيف تتعامل سَمِت للاستشارات القانونية مع البيانات المرتبطة بموقعها وطلبات التواصل، بما فيها البريد الإلكتروني وواتساب، وما يمكنك طلبه بشأن بياناتك. ويخضع الملف الذي يُقبل للعمل كذلك لمتطلبات التكليف والالتزامات المهنية والقانونية المرتبطة به.',
            'ru' => 'Настоящая политика разъясняет, как Summit Legal Consultancy обрабатывает данные, связанные с сайтом и обращениями через электронную почту и WhatsApp, а также с какими запросами вы можете обратиться в отношении своих данных. Дела, принятые в работу, дополнительно регулируются условиями поручения и связанными с ним профессиональными и правовыми обязанностями.',
        ]],
        // Privacy Policy, section 4 (the form fields described)
        ['item' => 7, 'set' => 'pages.L01.content.sections.3.blocks.0.text', 'value' => [
            'en' => 'Without a contact method, we may be unable to respond to your request.',
            'ar' => 'عدم تقديم وسيلة تواصل قد يمنعنا من الرد على طلبك.',
            'ru' => 'Без контактных данных мы можем оказаться не в состоянии ответить на ваш запрос.',
        ]],
        // Privacy Policy, section 5
        ['item' => 7, 'set' => 'pages.L01.content.sections.4.blocks.1.text', 'value' => [
            'en' => 'We do not request passwords, verification codes, private keys or a digital wallet recovery phrase. We handle documents according to the limits of necessity, confidentiality and applicable obligations. Sending documents does not in itself mean that an engagement has been accepted, and declining an engagement does not permit their use for an unlawful purpose.',
            'ar' => 'لا نطلب كلمات مرور أو رموز تحقق أو مفاتيح خاصة أو عبارة استرداد محفظة رقمية. ونتعامل مع المستندات وفق حدود الحاجة والسرية والالتزامات المنطبقة. إرسالها لا يعني وحده قبول التكليف، كما أن عدم قبول التكليف لا يبيح استخدامها خارج غرض مشروع.',
            'ru' => 'Мы не запрашиваем пароли, коды подтверждения, закрытые ключи или фразу восстановления цифрового кошелька. Документы обрабатываются в пределах необходимого, с соблюдением конфиденциальности и применимых обязанностей. Само направление документов не означает принятия поручения, а отказ от него не даёт права использовать документы без правомерной цели.',
        ]],
        // Terms and Conditions, heading 6
        ['item' => 7, 'set' => 'pages.L02.content.sections.5.heading', 'value' => [
            'en' => '6. Electronic Communications',
            'ar' => '٦. التواصل الإلكتروني',
            'ru' => '6. Электронная связь',
        ]],
        // Terms and Conditions, section 7
        ['item' => 7, 'set' => 'pages.L02.content.sections.6.blocks.0.text', 'value' => [
            'en' => 'The fees, scope and payment arrangements for a consultation or service are set out in the relevant agreement. The availability of a contact link does not mean that legal review is free or that an appointment has been confirmed.',
            'ar' => 'تُحدد أتعاب أي استشارة أو خدمة ونطاقها وطريقة سدادها في الاتفاق الخاص بها. ولا تعني إتاحة رابط تواصل أن المراجعة القانونية مجانية أو أن موعدًا أصبح مؤكدًا.',
            'ru' => 'Вознаграждение за консультацию или услугу, её объём и порядок оплаты определяются соответствующим соглашением. Наличие ссылки для связи не означает бесплатной юридической проверки или подтверждения записи на консультацию.',
        ]],
        // PDF items 10 to 12: the English and Russian pages link to the English version of an official page where the
        // same official site publishes it (checked 2026-11-25: each /en/ address opens the same page in English); the
        // Arabic pages keep the Arabic official pages (item 11). Not switched, because their English pages could not be
        // checked from here (the sites refuse automated visits): uaelegislation.gov.ae, centralbank.ae, mohre.gov.ae.
        ['item' => 10, 'replace' => 'https://u.ae/ar/', 'with' => 'https://u.ae/en/', 'locales' => ['en', 'ru']],
        ['item' => 10, 'replace' => 'https://tax.gov.ae/ar/', 'with' => 'https://tax.gov.ae/en/', 'locales' => ['en', 'ru']],
        ['item' => 10, 'replace' => 'https://dubailand.gov.ae/ar/', 'with' => 'https://dubailand.gov.ae/en/', 'locales' => ['en', 'ru']],
        ['item' => 10, 'replace' => 'https://www.adjd.gov.ae/ar/', 'with' => 'https://www.adjd.gov.ae/en/', 'locales' => ['en', 'ru']],
        ['item' => 10, 'replace' => 'https://www.moet.gov.ae/ar/', 'with' => 'https://www.moet.gov.ae/en/', 'locales' => ['en', 'ru']],
        ['item' => 10, 'replace' => 'https://www.moj.gov.ae/ar/', 'with' => 'https://www.moj.gov.ae/en/', 'locales' => ['en', 'ru']],
    ],
];
