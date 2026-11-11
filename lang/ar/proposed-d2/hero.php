<?php

/*
| نص مقترح لواجهة الصفحة الرئيسية في التصميم 2 (الملف + ابدأ موضوعك): غير موجود في ملفات DOCX المعتمدة،
| ويحتاج إلى موافقة العميل. PROPOSED COPY (Design 2 home hero), needs client approval.
| Read as d2p('hero.<key>'); plurals with trans_choice (six Arabic forms).
*/

return [
    'stage_label' => 'رسم توضيحي: نماذج عامة لمستندات من الأنواع التي تراجعها سَمِت عادةً، مرتّبة كملف قضية. ليست مستندات حقيقية.',
    'caption' => 'نماذج لمستندات تراجعها سَمِت عادةً',
    'callouts_label' => 'ما نبرزه في هذا النموذج',

    'prev' => 'المستند السابق',
    'next' => 'المستند التالي',
    'segments_label' => 'اختر نموذج مستند',

    'tool' => [
        'title' => 'ابدأ موضوعك',
        'audience_label' => 'لمن هذا الطلب؟',
        'audience' => [
            'individual' => 'فرد',
            'business' => 'شركة',
            'investor' => 'مستثمر',
        ],
        'situation_label' => 'وضعك',
        'situation_placeholder' => 'اختر وضعك',
        // الصيغ الست: صفر | واحد | اثنان | 3–10 | 11–99 | 100 فأكثر
        'documents' => 'لا توجد مستندات مطلوبة عادةً|مستند واحد مطلوب عادةً|مستندان مطلوبان عادةً|:count مستندات مطلوبة عادةً|:count مستندًا مطلوبًا عادةً|:count مستند مطلوب عادةً',
        'picks_label' => 'حالات شائعة',
        'documents_label' => 'المستندات المطلوبة عادةً',
        'documents_empty' => 'اختر وضعك لتظهر هنا.',
        'continue' => 'متابعة',
    ],
];
