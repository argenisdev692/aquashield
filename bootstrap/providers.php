<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Modules\Backups\Providers\BackupsServiceProvider;
use Modules\CompanyData\Providers\CompanyServiceProvider;
use Modules\Users\Providers\UsersServiceProvider;
use Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    ActivityLogServiceProvider::class,
    BackupsServiceProvider::class,
    CompanyServiceProvider::class,
    UsersServiceProvider::class,
    SharedServiceProvider::class,
];
