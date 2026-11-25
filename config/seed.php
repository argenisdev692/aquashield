<?php

declare(strict_types=1);

return [

    /*
     * Local/testing demo users only. UserSeeder returns immediately outside
     * local/testing and when this value is empty. Never set it in a shared
     * environment; production administrators are provisioned explicitly.
     */
    'user_password' => env('SEED_USER_PASSWORD'),

];
