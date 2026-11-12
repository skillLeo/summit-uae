<?php

/*
| Dashboard (docs/briefs/design-2.1-and-admin-cms.md §11.1): today's work, not analytics. Staff-facing admin labels,
| not public copy. Keep the keys identical in lang/{en,ar,ru}/dashboard.php.
*/

return [
    'title' => 'Сводка',
    'subheading' => 'Работа на сегодня. Спам никогда не учитывается; время дубайское.',

    'tile' => [
        'new' => 'Новые заявки, последние 7 дней',
        'waiting' => 'Ждут первого рассмотрения',
        'ttfr' => 'Медиана до первого рассмотрения, 30 дней',
        'closed' => 'Закрыто, последние 30 дней',
    ],

    'line' => [
        'change' => ':change к предыдущим 7 дням',
        'change_percent' => ':change (:percent) к предыдущим 7 дням',
        'small_base' => ':change к предыдущим 7 дням (малая база)',
        'no_earlier' => ':change к предыдущим 7 дням (нет данных раньше)',
        'oldest' => 'Дольше всех ждёт: :hours рабочих ч',
        'none_waiting' => 'Никто не ждёт',
        'p90' => 'P90 :hours ч. n = :n, не рассмотрено: :unreviewed',
        'no_reviews' => 'Ни одна заявка за последние 30 дней ещё не рассмотрена (ждут: :unreviewed)',
        'engagement' => 'Доля договоров :rate% (n = :n)',
        'engagement_hidden' => 'Доля договоров видна от 5 закрытых с итогом (n = :n)',
        'hours' => ':hours ч',
        'ttfr_change' => ':change ч к предыдущим 30 дням',
        'up' => 'Рост',
        'down' => 'Снижение',
        'same' => 'Без изменений',
        'better' => 'лучше',
        'worse' => 'хуже',
    ],

    'chart' => [
        'heading' => 'Заявки, последние 30 дней',
        'description' => 'Получено за день, по времени Дубая.',
        'series' => 'Заявки',
        'empty' => 'За этот период заявок нет',
        'change_range' => 'Изменить период',
        'reports' => 'Открыть отчёты',
    ],

    'attention' => [
        'heading' => 'Требует внимания',
        'over_target' => 'Внутренний срок превышен',
        'unassigned' => 'Без ответственного',
        'deadline' => 'Срок в ближайшие 7 дней',
        'none' => 'Сейчас ничего не требует внимания.',
        'waited' => ':hours рабочих ч',
        'more' => '{1} и ещё 1|[2,*] и ещё :count',
        'open_inbox' => 'Открыть входящие',
    ],

    'latest' => [
        'heading' => 'Последние заявки',
        'all' => 'Все заявки',
    ],

    'content' => [
        'heading' => 'Состояние контента',
        'drafts' => 'Черновики ждут публикации',
        'scheduled' => 'Запланирована публикация',
        'pending' => 'Ждут одобрения клиента',
        'missing' => 'Не хватает переводов',
        'missing_locale' => ':language',
        'open' => 'Открыть',
    ],
];
