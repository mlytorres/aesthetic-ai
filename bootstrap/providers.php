<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SentryContextServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SentryContextServiceProvider::class,
];
