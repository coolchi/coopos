<?php

use App\Http\Middleware\BindTenantAndBranch;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenantFromHost;
use App\Http\Middleware\InitializeTenant;
use App\Support\Tenancy;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/platform.php'));
            Tenancy::registerRoutes();
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            IdentifyTenantFromHost::class,
            BindTenantAndBranch::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->redirectUsersTo(fn () => auth()->user()?->homeUrl() ?? '/');

        $middleware->trustHosts(at: function () {
            $domain = Tenancy::centralDomain();

            return array_filter([$domain, 'www.'.$domain, '*.'.$domain, 'localhost', '127.0.0.1']);
        });

        // Behind Cloudflare + nginx proxy to Docker (host network on :8088)
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            IdentifyTenantFromHost::class,
        );

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            BindTenantAndBranch::class,
        );

        $middleware->alias([
            'tenant' => InitializeTenant::class,
            'superadmin' => EnsureSuperAdmin::class,
            'central' => EnsureCentralDomain::class,
            'tenant.staff' => EnsureTenantStaff::class,
            'tenant.member' => EnsureMember::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
