<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\ConsoleServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Modules\AccessControl\Providers\AccessControlServiceProvider;
use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Modules\Backups\Providers\BackupsServiceProvider;
use Modules\CompanyData\Providers\CompanyServiceProvider;
use Modules\LeadAppointment\Providers\LeadAppointmentServiceProvider;
use Modules\Users\Providers\UsersServiceProvider;
use Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    ConsoleServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    AccessControlServiceProvider::class,
    ActivityLogServiceProvider::class,
    BackupsServiceProvider::class,
    CompanyServiceProvider::class,
    LeadAppointmentServiceProvider::class,
    UsersServiceProvider::class,
    SharedServiceProvider::class,
];
