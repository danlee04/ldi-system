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

];
