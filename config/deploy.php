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
    | PHP CLI binary
    |--------------------------------------------------------------------------
    |
    | Under php-fpm, PHP_BINARY points to the FPM daemon. Leave empty to auto-
    | detect `php` on PATH, or set an absolute path (e.g. /usr/bin/php8.3).
    |
    */
    'php_binary' => env('DEPLOY_PHP_BINARY'),

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
