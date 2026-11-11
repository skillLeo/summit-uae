<?php

/*
| Design 2 web app manifest (public/d2/manifest-{locale}.webmanifest, written by scripts/d2/pwa-icons.mjs):
| PROPOSED copy, not in the approved DOCX files. Listed in docs/proposed-copy.md for approval.
| The manifest's "name" is the approved brand name (ui('brand.name')) and its "description" the approved
| home meta description (P01 meta_description); only the short name is new.
*/

return [
    // Label under the home-screen icon once the site is installed (upgrade spec §5.5 and §12 E:
    // short_name "Summit Legal"). Short enough not to be cut on Android and iOS home screens.
    'short_name' => 'Summit Legal',
];
