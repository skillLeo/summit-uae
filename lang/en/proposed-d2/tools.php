<?php

/*
| Design 2 home tools (spec §12 C6, §5.2, §5.3): the gratuity estimator and the Dubai rent increase check.
| PROPOSED COPY, not in the approved DOCX files: needs client approval in EN/AR/RU, and the calculator
| labels, notes, legal references and disclaimers need a lawyer's review before launch
| (docs/proposed-copy.md). Read as d2p('tools.<key>'). The consultation button uses the approved
| ui('cta.request_consultation').
|
| Placeholders (:amount, :days …) are filled in the browser; plurals (common.days) follow trans_choice()
| with every form of the language. Never state a claim time limit here (spec §5.2: not until a lawyer
| confirms it).
*/

return [
    'section' => [
        'title' => 'Check the Figures Yourself',
        'intro' => 'Two quick estimates that run in your browser: the end-of-service gratuity and final settlement under the UAE Labour Law, and the Dubai rent increase check. Nothing you enter leaves this page unless you add it to a consultation request.',
    ],

    // The calculators page (Phase 2: the estimators moved off the home page, brief §5.1); noindex while pending.
    'page' => [
        'seo_title' => 'Check the Figures Yourself | :brand',
        'meta_description' => 'Estimate an end-of-service gratuity under the UAE Labour Law or check a Dubai rent increase. The figures are calculated in your browser.',
    ],

    // The tabs above the two tools (D35: underline tabs).
    'switch' => [
        'label' => 'Choose a tool',
        'gratuity' => 'Gratuity estimator',
        'rent' => 'Rent increase check',
    ],

    // Links to the calculators page (Phase 2, brief §5.1): home section 09, Employment Law (S06, the gratuity
    // estimator) and Property and Tenancy (S05, the rent increase check). Shown only while pending copy is shown.
    'links' => [
        'page' => 'Estimate an end-of-service gratuity or a Dubai rent increase',
        'gratuity' => 'Estimate your end-of-service gratuity',
        'rent' => 'Check a Dubai rent increase',
    ],

    'common' => [
        // Shown in the result card when JavaScript is off (the inputs and the consultation button still work).
        'nojs' => 'This estimate is calculated in your browser and needs JavaScript. You can still request a consultation, and we will go through the figures with you.',
        // Placeholder for an amount that is not calculated yet.
        'empty_value' => '—',
        'copy' => 'Copy summary',
        'copied' => 'Summary copied',
        'copy_failed' => 'The summary could not be copied. Select the figures and copy them yourself.',
        'check_fields' => 'Check the highlighted fields to see the estimate.',
        'legal_basis' => 'Legal basis:',
        // Last line of every copied or prefilled summary.
        'estimate_only' => 'Estimate only, not legal advice.',
        'not_entered' => 'not entered',
        'days' => ':count day|:count days',
    ],

    // Field errors (codes from resources/js/d2/tools/gratuity.js and rent.js).
    'errors' => [
        'date' => 'Enter a complete date between 1900 and 2100.',
        'end_before_start' => 'The last working day must be on or after the start date.',
        'not_number' => 'Enter a number, for example 10000.',
        'negative' => 'Enter 0 or more.',
        'positive' => 'Enter an amount greater than 0.',
        'too_large' => 'Enter a smaller number.',
        'whole_days' => 'Enter whole days.',
        'absence_exceeds_service' => 'Unpaid absence must be shorter than the time from the start date to the last working day.',
        'total_below_basic' => 'The total wage includes the basic wage, so it cannot be lower than the basic wage.',
        'hours' => 'Enter the hours per week, from 1 to 168.',
        'hours_exceed' => 'Part-time hours cannot be more than the full-time hours.',
        'notice_range' => 'A contractual notice period is 30 to 90 days.',
    ],

    'gratuity' => [
        'title' => 'End-of-service gratuity estimator',
        'scope' => [
            'label' => 'Where do you work?',
            'mainland' => 'Mainland private sector',
            'freezone' => 'Non-financial free zone',
            'difc' => 'DIFC',
            'adgm' => 'ADGM',
            'government' => 'Government',
            'domestic' => 'Domestic worker',
        ],
        // DIFC, ADGM, government and domestic work: the estimator does not apply (spec §5.2 step 0).
        'out' => [
            'title' => 'Outside this estimator',
            'text' => 'This estimator covers the UAE Labour Law only. Your situation follows different rules — we can review it with you.',
            'summary' => 'End-of-service gratuity question. Where I work: :scope.',
        ],
        'groups' => [
            'service' => 'Service',
            'wage' => 'Wage',
            'other' => 'Leave, notice and unpaid wages',
        ],
        'fields' => [
            'start' => 'Start date',
            'end' => 'Last working day',
            'contract' => 'Contract type',
            'contract_full' => 'Full-time',
            'contract_part' => 'Part-time',
            'contract_temporary' => 'Temporary',
            'absence' => 'Unpaid absence days',
            'absence_hint' => 'Not counted as service',
            'hours' => 'Contract hours per week',
            'hours_hint' => 'As in your contract',
            'full_hours' => 'Full-time hours per week',
            'full_hours_hint' => 'For the same job',
            'basic' => 'Last basic monthly wage (AED)',
            'basic_hint' => 'Without allowances',
            'total' => 'Total monthly wage (AED)',
            'total_hint' => 'Basic wage plus allowances, for the cap and the notice',
            'leave' => 'Unused leave days',
            'leave_hint' => 'Days you did not take',
            'unpaid' => 'Unpaid wages (AED)',
            'unpaid_hint' => 'Earned but not yet paid',
            'notice' => 'Notice period in the contract (days)',
            'notice_hint' => '30 to 90 days',
            'served' => 'Notice days worked',
            'served_hint' => 'Days of the notice period already worked',
        ],
        // Example values shown greyed in the empty fields (spec §5.2 "States: empty").
        'placeholders' => [
            'basic' => '10,000',
            'total' => '12,000',
            'hours' => '24',
            'full_hours' => '48',
            'days' => '0',
            'notice' => '30',
            'amount' => '0',
        ],
        'result' => [
            'empty' => 'Enter the start date, the last working day and the basic wage to see an estimate.',
            // Part-time, when only the two hour fields are still empty.
            'empty_hours' => 'Enter the contract hours and the full-time hours to see the part-time estimate.',
            'total' => 'Estimated total',
            'caption' => 'Breakdown of the estimate',
            'col_item' => 'Item',
            'col_amount' => 'Amount',
            'gratuity' => 'End-of-service gratuity',
            'part21' => '21 days’ basic wage for each of the first five years',
            'part30' => '30 days’ basic wage for each further year',
            'leave' => 'Leave encashment',
            'notice' => 'Notice compensation',
            'unpaid' => 'Unpaid wages',
            'may_apply' => 'May apply',
            'notice_note' => 'Depends on who ended the contract and whether the notice period was worked.',
            'cap' => 'Cap applied',
            'cap_basic' => 'Cap based on basic wage',
            'cap_note' => 'Limited to two years’ wage (24 months): :amount.',
            'cap_basic_note' => 'Without a total monthly wage, the cap and the notice compensation use the basic wage.',
            'under_year' => 'The gratuity applies after one year of continuous service.',
            'temporary' => 'The gratuity does not apply to temporary work.',
            'part_time' => 'Part-time: in proportion to the hours, :percent of the full-time amount.',
            // Art. 53 (proposed wording; legal review).
            'payment' => 'The employer pays wages and entitlements within 14 days of the end of the contract (Art. 53).',
            'references' => 'Federal Decree-Law No. 33 of 2021, Art. 51, 53, 43, 29; Cabinet Resolution No. 1 of 2022, Art. 19, 30.',
            'disclaimer' => 'Estimate only, based on Federal Decree-Law No. 33 of 2021. It is not legal advice and does not replace a review of your contract and records.',
            // Mobile: the total pinned above the tab bar while the inputs are on screen.
            'see_breakdown' => 'See breakdown',
        ],
        'timeline' => [
            'service' => 'Service counted',
            'value' => ':days (:years)',
            // Always shown with two decimals ("7.17 years").
            'years' => ':count years',
            'zone21' => '21 days/yr',
            'zone30' => '30 days/yr',
            'one_year' => '1 year: the gratuity starts',
            'absence' => 'Unpaid absence, not counted: :days',
        ],
        // Screen-reader announcement after the visitor stops typing.
        'live' => 'Estimated total: :amount.',
        // Plain-text recap for "Copy summary" and the consultation form (one line each).
        'summary' => [
            'title' => 'End-of-service gratuity estimate (:scope)',
            'service' => 'Service: :start to :end, :days counted (:years); contract: :contract',
            'absence' => 'Unpaid absence: :days',
            'wage' => 'Last basic monthly wage: :basic; total monthly wage: :total',
            'gratuity' => 'End-of-service gratuity: :amount (21-day part :part21; 30-day part :part30)',
            'others' => 'Leave encashment: :leave; notice compensation (may apply): :notice; unpaid wages: :unpaid',
            'total' => 'Estimated total: :amount',
        ],
    ],

    'rent' => [
        'title' => 'Dubai rent increase check',
        'fields' => [
            'current' => 'Current annual rent (AED)',
            'current_hint' => 'As in your tenancy contract',
            'average' => 'Average annual rent in the DLD Rental Index (AED)',
            'average_hint' => 'Look up similar properties in the official index, then enter the average rent it shows.',
            'expiry' => 'Contract expiry date',
            'notice' => 'Date you received the increase notice',
        ],
        'placeholders' => [
            'current' => '80,000',
            'average' => '100,000',
        ],
        // The official Dubai Land Department Rental Index service (opens in a new tab). PROPOSED LINK:
        // confirm with the client; the DLD site has English and Arabic versions only.
        'dld' => [
            'label' => 'Open DLD Rental Index',
            'url' => 'https://dubailand.gov.ae/en/eservices/rental-index/',
        ],
        'result' => [
            'empty' => 'Enter your current rent and the index average to see the maximum increase.',
            'max' => 'Maximum increase',
            'per_year' => ':amount a year',
            'new_rent' => 'New annual rent up to :amount',
            'gap_below' => 'Your rent is :percent below the index average.',
            'gap_not_below' => 'Your rent is not below the index average.',
            'scale' => 'Increase bands',
            'scale_gap' => 'Below the average',
            'scale_increase' => 'Maximum increase',
            'your_band' => 'Your band',
            'notice_title' => '90-day notice',
            'notice_ok' => 'Notice received :days before the expiry date: at least 90 days.',
            'notice_short' => 'Notice received :days before the expiry date. The increase may not apply at this renewal — check your contract terms.',
            'notice_after' => 'Notice received after the expiry date. The increase may not apply at this renewal — check your contract terms.',
            'notice_missing' => 'Add the expiry date and the date you received the notice to check the 90-day notice.',
            'scope' => 'Dubai only, for residential properties; other emirates have different rules. The averages come from the Dubai Land Department’s Smart Rental Index, in use since January 2025.',
            'references' => 'Increase bands: Decree No. 43 of 2013. Notice: Law No. 26 of 2007, Art. 14, as amended by Law No. 33 of 2008.',
            'disclaimer' => 'Estimate only. It is not legal advice and does not replace a review of your tenancy contract.',
        ],
        // The five steps of the scale: how far the rent is below the average → the maximum increase.
        'bands' => [
            ['gap' => 'Up to 10%', 'increase' => '0%'],
            ['gap' => '10–20%', 'increase' => '5%'],
            ['gap' => '20–30%', 'increase' => '10%'],
            ['gap' => '30–40%', 'increase' => '15%'],
            ['gap' => 'Over 40%', 'increase' => '20%'],
        ],
        'live' => 'Maximum increase: :percent, :amount a year.',
        'summary' => [
            'title' => 'Dubai rent increase check',
            'rents' => 'Current annual rent: :current; DLD Rental Index average: :average',
            'result' => 'Maximum increase: :percent (:amount a year; new annual rent up to :rent)',
            'dates' => 'Contract expiry: :expiry; increase notice received: :notice',
        ],
    ],
];
