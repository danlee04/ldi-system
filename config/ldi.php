<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Read-only legacy sources
    |--------------------------------------------------------------------------
    |
    | Overridden in tests so importers can read fixture tables instead of a
    | real database. Nothing in this project may write to either schema.
    |
    */

    'hris_connection' => 'hris',

    'hris_tables' => [
        'employees' => 'employees',
        'users' => 'users',
    ],

    'legacy_connection' => 'legacy',

    'legacy_tables' => [
        'employees' => 'employees',
        'trainings' => 'trainings',
        'ldi_training' => 'ldi_training',
    ],

    /*
    |--------------------------------------------------------------------------
    | Picklists
    |--------------------------------------------------------------------------
    |
    | The choices HR sees when recording a planned training. Every list also
    | accepts free text through its "Other" option, so an unlisted partner
    | never blocks anybody; add the ones that recur here.
    |
    */

    'development_partners' => [
        'Department of Health',
        'Drug Treatment and Rehabilitation Center Caraga',
        'World Health Organization',
        'Local Government Unit',
    ],

    'facilitators' => [
        'Drug Treatment and Rehabilitation Center Caraga',
    ],

    'training_types' => [
        'Training',
        'Workshop',
        'Orientation',
        'Seminar',
    ],

    'training_communications' => [
        'Requested Training',
        'Training Invitation / Letter',
        'In-House Training',
        'DOH Central Office Communication / Invitation',
        'CHD Caraga Communication / Invitation',
    ],

    'budget_sources' => [
        'Human Resource',
    ],

    /*
    |--------------------------------------------------------------------------
    | DOH training report
    |--------------------------------------------------------------------------
    |
    | The submitted form counts participants in three TRC categories. They
    | are defined here rather than in code because who counts as a doctor,
    | and which divisions are administrative, is an agency decision.
    |
    */

    'pds' => [
        'template' => 'CS Form No. 212 Revised 2026 Personal Data Sheet PDS.xlsx',
    ],

    'doh' => [
        'office' => 'DRUG TREATMENT AND REHABILITATION CENTER CARAGA',

        // MCC: the doctors, matched on the exact position title.
        'mcc_positions' => [
            'CHIEF OF HOSPITAL III',
            'MEDICAL OFFICER III',
            'MEDICAL SPECIALIST I',
            'MEDICAL SPECIALIST II',
        ],

        // DM: the treatment division.
        'dm_division_codes' => ['RITD'],

        // Ad. Staff: administrative and finance.
        'ad_staff_division_codes' => ['AD', 'FA'],

        'signatories' => [
            ['role' => 'Prepared By:', 'name' => 'JESSA JOY D. GULLES, RPm', 'title' => 'Administrative Assistant III / Designate TS'],
            ['role' => 'Reviewed By:', 'name' => 'MARY JANE E. LAO GUICO', 'title' => 'Statistician II / OIC HRMO'],
            ['role' => 'Noted By:', 'name' => 'KATHLEEN L. ONDE, MMPA', 'title' => 'Supervising Administrative Officer'],
            ['role' => 'Approved By:', 'name' => 'EDHEL S. MIRO, MD, DPCAM, MM-PA', 'title' => 'Chief of Hospital III'],
        ],
    ],

];
