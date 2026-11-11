<?php

// Admin CMS: Pages, Legal Guides and guide categories (staff-facing; keys identical in lang/{en,ar,ru}/cms_pages.php).
return [
    'pages' => [
        'singular' => 'page',
        'plural' => 'Pages',
        'description' => 'Every public page is a list of blocks. The order, the switches and the anchors are shared by the three languages.',
        'key' => 'Page',
        'h1' => 'Page title (H1)',
        'breadcrumb' => 'Breadcrumb',
        'whatsapp_draft' => 'WhatsApp message of this page',
        'last_updated' => 'Last updated line',
        'section_page' => 'Page',
    ],
    'guides' => [
        'singular' => 'guide',
        'plural' => 'Legal Guides',
        'title' => 'Guide title',
        'home_title' => 'Homepage card title',
        'home_summary' => 'Homepage card summary',
        'card_title' => 'Legal Resources card title',
        'card_summary' => 'Legal Resources card summary',
        'category' => 'Category',
        'standalone' => 'Own page (later)',
        'standalone_hint' => 'Reserved for a standalone guide page. Today every guide is shown on Legal Resources at its anchor.',
        'section_cards' => 'Cards',
        'section_guide' => 'Guide',
        'body' => 'Guide body',
        'new_body_hint' => 'Save the guide first; its body sections can then be written block by block.',
    ],
    'categories' => [
        'singular' => 'guide category',
        'plural' => 'Guide categories',
        'name' => 'Name',
        'guides' => 'Guides, in order',
    ],
];
