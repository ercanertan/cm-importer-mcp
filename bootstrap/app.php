<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'role.super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'role.org_admin' => \App\Http\Middleware\EnsureOrgAdmin::class,
            'org.membership' => \App\Http\Middleware\EnsureOrgMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
