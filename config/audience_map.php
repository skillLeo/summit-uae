<?php

/*
|--------------------------------------------------------------------------
| Design 2 audience → service mapping (PROPOSED: needs client approval)
|--------------------------------------------------------------------------
| Used by the hero "Start your matter" tool card (three tabs), the practice-area filter
| chips and the Who We Help panels. Derived from Design 1's proposed Navigator mapping
| (config/summit.php → navigator_audiences): individual = individuals + residents,
| business = businesses + entrepreneurs, investor = investors. If an audience is not
| mapped, the tool shows every situation grouped by service.
*/

return [

    'hero' => [
        'individual' => ['S01', 'S02', 'S03', 'S05', 'S06', 'S08', 'S10'],
        'business' => ['S01', 'S04', 'S06', 'S07', 'S09', 'S10', 'S11'],
        'investor' => ['S03', 'S04', 'S05', 'S07', 'S09'],
    ],

    // The five approved Who We Help audiences (P01 who_we_help.items[].key) → related services.
    'who' => [
        'individuals' => ['S01', 'S02', 'S05', 'S06', 'S08'],
        'businesses' => ['S01', 'S04', 'S06', 'S07', 'S11'],
        'investors' => ['S03', 'S04', 'S05', 'S07', 'S09'],
        'residents' => ['S05', 'S06', 'S08', 'S10'],
        'entrepreneurs' => ['S04', 'S07', 'S09', 'S11'],
    ],

];
