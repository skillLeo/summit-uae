<?php

/*
| Dashboard (docs/briefs/design-2.1-and-admin-cms.md §11.1): today's work, not analytics. Staff-facing admin labels,
| not public copy. Keep the keys identical in lang/{en,ar,ru}/dashboard.php.
*/

return [
    'title' => 'لوحة المعلومات',
    'subheading' => 'عمل اليوم. لا تُحتسب الرسائل غير المرغوب فيها أبدًا؛ الأوقات بتوقيت دبي.',

    'tile' => [
        'new' => 'الطلبات الجديدة، آخر 7 أيام',
        'waiting' => 'بانتظار أول مراجعة',
        'ttfr' => 'الوسيط حتى أول مراجعة، آخر 30 يومًا',
        'closed' => 'المغلقة، آخر 30 يومًا',
    ],

    'line' => [
        'change' => ':change مقارنة بالأيام السبعة السابقة',
        'change_percent' => ':change (:percent) مقارنة بالأيام السبعة السابقة',
        'small_base' => ':change مقارنة بالأيام السبعة السابقة (قاعدة صغيرة)',
        'no_earlier' => ':change مقارنة بالأيام السبعة السابقة (لا بيانات سابقة)',
        'oldest' => 'أطول انتظار: :hours ساعة عمل',
        'none_waiting' => 'لا أحد في الانتظار',
        'p90' => 'P90 ‏:hours س. n = :n، و:unreviewed لم تُراجع بعد',
        'no_reviews' => 'لم يُراجع بعد أي طلب استُلم في آخر 30 يومًا (:unreviewed في الانتظار)',
        'engagement' => 'معدل التعاقد :rate% (n = :n)',
        'engagement_hidden' => 'يظهر معدل التعاقد ابتداءً من 5 طلبات مغلقة بنتيجة (n = :n)',
        'hours' => ':hours س',
        'ttfr_change' => ':change س مقارنة بالثلاثين يومًا السابقة',
        'up' => 'ارتفاع',
        'down' => 'انخفاض',
        'same' => 'دون تغيير',
        'better' => 'أفضل',
        'worse' => 'أسوأ',
    ],

    'chart' => [
        'heading' => 'الطلبات، آخر 30 يومًا',
        'description' => 'المستلمة يوميًا، بتوقيت دبي.',
        'series' => 'الطلبات',
        'empty' => 'لا توجد طلبات في هذه الفترة',
        'change_range' => 'تغيير الفترة',
        'reports' => 'فتح التقارير',
    ],

    'attention' => [
        'heading' => 'يحتاج إلى متابعة',
        'over_target' => 'تجاوز الهدف الداخلي',
        'unassigned' => 'غير مسند',
        'deadline' => 'موعد نهائي خلال 7 أيام',
        'none' => 'لا شيء يحتاج إلى متابعة الآن.',
        'waited' => ':hours ساعة عمل',
        'more' => '{1} وطلب آخر|[2,*] و:count طلبات أخرى',
        'open_inbox' => 'فتح صندوق الطلبات',
    ],

    'latest' => [
        'heading' => 'أحدث الطلبات',
        'all' => 'كل الطلبات',
    ],

    'content' => [
        'heading' => 'حالة المحتوى',
        'drafts' => 'مسودات بانتظار النشر',
        'scheduled' => 'مجدولة للنشر',
        'pending' => 'بانتظار موافقة العميل',
        'missing' => 'ترجمات ناقصة',
        'missing_locale' => ':language',
        'open' => 'فتح',
    ],
];
