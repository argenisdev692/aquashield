<?php

declare(strict_types=1);

return [

    /*
     * Local/testing demo users only. UserSeeder returns immediately outside
     * local/testing and when this value is empty. Never set it in a shared
     * environment; production administrators are provisioned explicitly.
     */
    'user_password' => env('SEED_USER_PASSWORD'),

    /*
     * One value for the first demo SUPER_ADMIN (UserSeeder) and the demo
     * CompanyData owner. The second SUPER_ADMIN stays hardcoded in UserSeeder.
     * When null/empty, UserSeeder's built-in demo owner is used and
     * CompanyDataSeeder falls back to the first active SUPER_ADMIN.
     */
    'company_owner_email' => env('SEED_COMPANY_OWNER_EMAIL'),

];
