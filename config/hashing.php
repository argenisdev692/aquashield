<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | OWASP baseline §2 / §4 requires Argon2id. Laravel's skeleton default is
    | bcrypt, so this file is published deliberately — without it the app
    | silently runs on bcrypt.
    |
    | Confirmed 2026-09-27: config('hashing.driver') was 'bcrypt' before this
    | file existed. See .opencode/skills/owasp/SKILL.md §17.
    |
    */

    'driver' => 'argon2id',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],

];
