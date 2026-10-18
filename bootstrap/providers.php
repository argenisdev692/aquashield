<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    SharedServiceProvider::class,
];
