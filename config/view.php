<?php

/*
|--------------------------------------------------------------------------
| View paths
|--------------------------------------------------------------------------
| The public site's templates live in resources/views/designs/d2 (Design 2, the only design since the owner retired
| Design 1 on 2026-11-25; Design 1 is archived at the git tags design-1 and design-1.1). That folder is searched first,
| so its pages/home, pages/preview, errors/404 … are the site's pages; resources/views holds everything else (the
| admin's views under filament/, the e-mails under mail/, the sitemap). Laravel's error handler builds the errors::
| namespace from this list too, so designs/d2/errors/{status} replace the stock error pages and any status Design 2
| lacks keeps the stock page. Design 2 internals are referenced through the d2:: namespace
| (App\Providers\DesignServiceProvider). The other keys (compiled) are the framework's defaults.
*/

return [

    'paths' => [
        resource_path('views/designs/d2'),
        resource_path('views'),
    ],

];
