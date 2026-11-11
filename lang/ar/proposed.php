<?php

/*
| نص مقترح: غير موجود في ملفات DOCX المعتمدة، ويحتاج إلى موافقة العميل.
| PROPOSED COPY, needs client approval. Listed in docs/proposed-copy.md.
*/

return [
    'nav' => [
        'main' => 'التنقل الرئيسي',
        'quick' => 'التنقل السريع',
        'breadcrumb' => 'مسار التنقل',
    ],
    'external' => [
        'new_tab' => '(يُفتح في علامة تبويب جديدة)',
    ],
    'suggest' => [
        'message' => 'هذه الصفحة متاحة باللغة العربية.',
        'action' => 'عرض بالعربية',
        'dismiss' => 'إغلاق',
    ],
    'hours' => [
        'open' => 'مفتوح الآن · بتوقيت الإمارات',
        'closed' => 'مغلق الآن · بتوقيت الإمارات',
    ],
    'navigator' => [
        'heading' => 'ما موقفك؟',
        'intro' => 'اختر الحالة الأقرب إلى وضعك لتعرف المستندات التي تساعد في المراجعة الأولى، والخطوات الأولى المفيدة، والأخطاء التي ينبغي تجنّبها. تبقى اختياراتك في هذا المتصفح ولا تُرسَل إلى أي جهة.',
        'step_audience' => 'من أنت؟',
        'audience_all' => 'الكل',
        'step_pick' => 'اختر وضعك',
        'step_file' => 'ملفك الأولي',
        'filter_label' => 'تصفية الحالات',
        'filter_placeholder' => 'اكتب كلمة، مثل: شيك',
        'no_results' => 'لا توجد حالة تطابق هذه الكلمة. جرّب كلمة أخرى أو امسح التصفية.',
        'clear_filter' => 'مسح التصفية',
        'show_file' => 'عرض الملف الأولي',
        'empty' => 'اختر حالة لعرض ملفها الأولي.',
        'your_situation' => 'وضعك',
        'saved_note' => 'تُحفظ علاماتك على هذا الجهاز فقط.',
        'clear_ticks' => 'مسح العلامات',
        'print' => 'طباعة القائمة',
        'copy_link' => 'نسخ الرابط',
        'link_copied' => 'تم نسخ الرابط',
        'change' => 'اختر حالة أخرى',
        'progress' => ':current من :total',
        'print_date' => 'أُعدّ في :date',
        'close' => 'إغلاق',
        'back' => 'رجوع',
    ],
    'team' => [
        'portrait_pending' => 'الصورة الشخصية لاحقاً',
    ],
    'sheet' => [
        'close' => 'إغلاق',
    ],
    'form' => [
        'reference' => 'رقم المرجع',
    ],
    'preview' => [
        'notice' => 'معاينة النموذج الأولي الأول. يُبنى المحتوى المعتمد الكامل لهذه الصفحة وتصميمها في المرحلة الثانية، وحتى ذلك الحين يمكنك طلب استشارة أو العودة إلى الصفحة الرئيسية.',
    ],
    'offline' => [
        'title' => 'لا يوجد اتصال بالإنترنت',
        'text' => 'هذه الصفحة غير متاحة دون اتصال. ما زال بإمكانك الاتصال بنا أو مراسلتنا عبر واتساب أو البريد الإلكتروني:',
    ],
] + [
    // Design 2 (docs/upgrade-plan.md): one file per area in proposed-d2/, read as proposed.d2.{area}.{key}.
    'd2' => (static function (string $dir): array {
        $areas = [];
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $areas[basename($file, '.php')] = require $file;
        }

        return $areas;
    })(__DIR__.'/proposed-d2'),
];
