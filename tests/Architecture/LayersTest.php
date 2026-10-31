<?php

/*
| Service-Repository layering (docs/16-backend-architecture.md §9).
*/

arch('entry points never query the database directly')
    ->expect([
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Policies',
    ])
    ->not->toUse([
        'App\Repositories',
        'Illuminate\Support\Facades\DB',
    ]);

arch('services depend on repository contracts, not Eloquent implementations')
    ->expect('App\Services')
    ->not->toUse('App\Repositories\Eloquent');

arch('repository contracts are interfaces')
    ->expect('App\Repositories\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('RepositoryInterface');

arch('eloquent repositories are named and typed consistently')
    ->expect('App\Repositories\Eloquent')
    ->classes()
    ->toHaveSuffix('Repository');

arch('support helpers are pure (no models, repositories or facades)')
    ->expect('App\Support')
    ->not->toUse([
        'App\Models',
        'App\Repositories',
        'App\Services',
        'Illuminate\Support\Facades',
    ]);

arch('DTOs are laravel-data objects')
    ->expect('App\DTOs')
    ->toExtend('Spatie\LaravelData\Data')
    ->toHaveSuffix('Data');

arch('business rule exceptions extend the domain exception')
    ->expect('App\Exceptions')
    ->classes()
    ->toExtend('App\Exceptions\DomainException')
    ->ignoring('App\Exceptions\Tenancy');

arch('services are not final so they can be mocked')
    ->expect('App\Services')
    ->classes()
    ->not->toBeFinal();

arch('our own layers use strict types')
    ->expect([
        'App\DTOs',
        'App\Enums',
        'App\Exceptions',
        'App\Policies',
        'App\Repositories',
        'App\Services',
        'App\Support',
        'App\Http\Controllers\Web',
        'App\Http\Controllers\Api',
    ])
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();
