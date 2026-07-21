<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deploy webhook secret
    |--------------------------------------------------------------------------
    |
    | Shared secret required in the X-Deploy-Secret header (or "secret" body
    | field) to trigger POST /api/deploy. Leave empty to disable the endpoint.
    |
    */
    'secret' => env('DEPLOY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Branch to pull
    |--------------------------------------------------------------------------
    */
    'branch' => env('DEPLOY_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Command timeouts (seconds)
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'git' => (int) env('DEPLOY_GIT_TIMEOUT', 120),
        'tests' => (int) env('DEPLOY_TEST_TIMEOUT', 300),
        'build' => (int) env('DEPLOY_BUILD_TIMEOUT', 300),
    ],
];
