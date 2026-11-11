<?php

/*
| spatie/laravel-translation-loader (docs/briefs/design-2.1-and-admin-cms.md §8.10): interface text from the database.
| Every translation group reads the published rows of ui_strings first; the lang files fill whatever the database does
| not hold (App\Models\Content\UiString, read through App\Content\ContentRepository's cached snapshot, never across
| languages). Design 1's proposed() strings and Design 2's d2p() strings both go through it.
*/

return [

    'translation_loaders' => [
        Spatie\TranslationLoader\TranslationLoaders\Db::class,
    ],

    'model' => App\Models\Content\UiString::class,

    'translation_manager' => Spatie\TranslationLoader\TranslationLoaderManager::class,

];
