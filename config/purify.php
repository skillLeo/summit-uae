<?php

use Stevebauman\Purify\Definitions\Html5Definition;

/*
| Rich text sanitising (docs/briefs/design-2.1-and-admin-cms.md §8.10, §12.2): HTMLPurifier through stevebauman/purify,
| on save (App\Content\Cms\Editing\RichText::clean() in the admin) and on render (the rich-text block components). Only
| the brief's allow-list survives: p, h2, h3, ul, ol, li, a[href|target|rel], strong, em, br, blockquote. No style, no
| class, no images, no scripts; links are http(s), mailto or tel only.
*/
return [

    'default' => 'default',

    'configs' => [

        'default' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,h2,h3,ul,ol,li,a[href|target|rel],strong,em,br,blockquote',
            'HTML.ForbiddenElements' => '',
            'HTML.TargetBlank' => false,
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer', 'nofollow'],
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],

    ],

    'definitions' => Html5Definition::class,

    'css-definitions' => null,

    'serializer' => [
        'driver' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),
        'cache' => \Stevebauman\Purify\Cache\CacheDefinitionCache::class,
    ],

];
