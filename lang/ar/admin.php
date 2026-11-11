<?php

/*
| لوحة الإدارة الداخلية (Filament، ‎/admin). تسميات واجهة الموظفين وليست نصوصًا عامة: لا تظهر على
| الموقع العام، لذلك ليست مفاتيح مقترحة. المحتوى المعتمد (عناوين الخدمات، خيارات اللغة ونوع المسألة،
| اسم العلامة) يُقرأ من استيراد ملفات DOCX عبر content()->ui() / service().
| أبقِ المفاتيح متطابقة في lang/{en,ar,ru}/admin.php.
*/

return [

    'brand' => [
        'panel' => 'لوحة الإدارة',
    ],

    'locale' => [
        'label' => 'لغة الواجهة',
        'switch' => 'اللغة',
        'en' => 'English',
        'ar' => 'العربية',
        'ru' => 'Русский',
        'saved' => 'تم تغيير لغة الواجهة.',
    ],

    'nav' => [
        'requests' => 'طلبات الاستشارة',
        'requests_short' => 'الطلبات',
        'request' => 'طلب استشارة',
    ],

    'dashboard' => [
        'title' => 'لوحة المعلومات',
        'subheading' => 'طلبات الاستشارة المرسلة عبر نموذج الموقع. الرسائل غير المرغوب فيها مستبعدة من جميع الأعداد.',
    ],

    'stats' => [
        'week' => 'المستلمة خلال آخر 7 أيام',
        'week_previous' => 'الأيام السبعة السابقة: :count',
        'month' => 'المستلمة خلال آخر 30 يومًا',
        'month_previous' => 'الثلاثون يومًا السابقة: :count',
        'awaiting' => 'بانتظار المراجعة',
        'awaiting_hint' => 'الحالة «جديد». افتح القائمة',
        'ratio' => 'نسبة المسودات إلى الطلبات المرسلة',
        'ratio_value' => 'غير متتبَّعة',
        'ratio_hint' => 'تبقى المسودات على جهاز الزائر عن قصد: لا يُرسل أي شيء ولا يُحتسب قبل الإرسال.',
    ],

    'by_service' => [
        'heading' => 'الطلبات حسب الخدمة',
        'description' => 'نوع المسألة الذي اختاره الزائر في النموذج.',
        'service' => 'الخدمة',
        'share' => 'النسبة من الإجمالي',
    ],

    'by_language' => [
        'heading' => 'الطلبات حسب اللغة',
        'description' => 'لغة الموقع: نسخة الموقع التي أُرسل منها النموذج. المفضلة: اللغة التي طلب الزائر التواصل بها.',
        'language' => 'اللغة',
        'site' => 'لغة الموقع',
        'preferred' => 'المفضلة',
        'total' => 'جميع اللغات',
    ],

    'latest' => [
        'heading' => 'أحدث الطلبات',
        'all' => 'جميع الطلبات',
    ],

    'period' => [
        'week' => '7 أيام',
        'month' => '30 يومًا',
        'all' => 'الإجمالي',
    ],

    'status' => [
        'new' => 'جديد',
        'in_review' => 'قيد المراجعة',
        'closed' => 'مغلق',
        'spam' => 'غير مرغوب فيه',
    ],

    'field' => [
        'reference' => 'الرقم المرجعي',
        'received' => 'تاريخ الاستلام',
        'status' => 'الحالة',
        'status_changed_at' => 'تاريخ تغيير الحالة',
        'name' => 'الاسم',
        'contact' => 'وسيلة التواصل',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'service' => 'الخدمة',
        'site_language' => 'لغة الموقع',
        'preferred_language' => 'اللغة المفضلة',
        'summary' => 'الملخص',
        'deadline_date' => 'الموعد النهائي أو تاريخ الجلسة',
        'deadline_details' => 'تفاصيل الموعد',
        'consent_at' => 'تاريخ الموافقة',
        'consent_text' => 'نص الموافقة المعروض',
        'attachment' => 'المرفق',
        'internal_note' => 'ملاحظة داخلية',
        'internal_note_hint' => 'تظهر في هذه اللوحة فقط، وتُحفظ مشفّرة.',
        'updated_at' => 'آخر تحديث',
    ],

    'value' => [
        'not_chosen' => 'لم يُحدَّد',
        'none' => '—',
        'not_given' => 'لم يُذكر',
    ],

    // PHP date characters (Carbon translatedFormat), Dubai time: App\Filament\Support\AdminFormat (Arabic comma)
    'format' => [
        'date' => 'j F Y',
        'date_short' => 'j M Y',
        'datetime' => 'j F Y، H:i',
        'time' => 'H:i',
    ],

    // decimal units, like the public form's "up to 10 MB" and its upload chip (AdminFormat::fileSize)
    'size' => [
        'kb' => ':size كيلوبايت',
        'mb' => ':size ميغابايت',
    ],

    'section' => [
        'request' => 'الطلب',
        'request_hint' => 'فُكّ تشفيره لهذه الصفحة فقط. أبقِ البيانات الشخصية داخل اللوحة.',
        'visitor' => 'الزائر',
        'review' => 'المراجعة',
        'record' => 'الموافقة والسجل',
        'attachment' => 'المرفق',
    ],

    'filter' => [
        'status' => 'الحالة',
        'service' => 'الخدمة',
        'language' => 'لغة الموقع',
        'received_from' => 'مستلم من',
        'received_until' => 'مستلم حتى',
        'from_indicator' => 'مستلم من :date',
        'until_indicator' => 'مستلم حتى :date',
        'any_date' => 'أي تاريخ',
    ],

    'action' => [
        'menu' => 'الإجراءات',
        'change_status' => 'تغيير الحالة',
        'mark_in_review' => 'وضع قيد المراجعة',
        'close' => 'إغلاق الطلب',
        'reopen' => 'إعادة الفتح',
        'download' => 'تنزيل المرفق',
        'open' => 'فتح',
    ],

    'notify' => [
        'status_changed' => 'تم تغيير الحالة إلى «:status».',
        'status_changed_many' => 'لم تتغير حالة أي طلب.|تم تغيير حالة طلب واحد.|تم تغيير حالة طلبين.|تم تغيير حالة :count طلبات.|تم تغيير حالة :count طلبًا.|تم تغيير حالة :count طلب.',
        'saved' => 'تم تحديث الطلب.',
        'file_missing' => 'لم يعد الملف موجودًا في التخزين.',
    ],

    'attachment' => [
        'disabled' => 'المرفقات معطّلة في النموذج العام (SUMMIT_ATTACHMENTS_ENABLED=false)، لذلك لا يمكن أن يصل أي ملف مع الطلب.',
        'none' => 'لم يُرفق أي ملف.',
        'meta' => ':type · :size',
        'download_hint' => 'يتم التنزيل داخل جلستك المسجّلة فقط: لا يوجد رابط قابل للمشاركة.',
    ],

    'spam' => [
        'note' => 'حُفظ كرسالة غير مرغوب فيها تلقائيًا لأن حقل المصيدة المخفي كان ممتلئًا. لم تُرسل أي رسالة إشعار.',
    ],

    'empty' => [
        'heading' => 'لا توجد طلبات استشارة بعد',
        'description' => 'تظهر هنا الطلبات المرسلة عبر نموذج الاستشارة في الموقع.',
        'filtered' => 'لا توجد طلبات تطابق عوامل التصفية هذه.',
    ],

    'login' => [
        'languages' => 'لغة الواجهة',
    ],

];
