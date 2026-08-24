<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class Tenancy
{
    public static function centralDomain(): string
    {
        return (string) (config('coopos.tenancy.central_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    public static function centralHosts(): array
    {
        $domain = self::centralDomain();

        return array_values(array_unique(array_filter([
            $domain,
            'www.'.$domain,
            'localhost',
            '127.0.0.1',
            '::1',
        ])));
    }

    public static function subdomainsEnabled(): bool
    {
        return filter_var(config('coopos.tenancy.subdomains'), FILTER_VALIDATE_BOOL)
            && ! in_array(self::centralDomain(), ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function isCentralHost(?string $host = null): bool
    {
        $host = self::normalizeHost($host ?? request()->getHost());

        return in_array($host, self::centralHosts(), true);
    }

    public static function isNamedCentralHost(?string $host = null): bool
    {
        $host = self::normalizeHost($host ?? request()->getHost());
        $domain = self::centralDomain();

        return in_array($host, [$domain, 'www.'.$domain], true);
    }

    public static function isTenantHost(?string $host = null): bool
    {
        return self::subdomainFromHost($host) !== null;
    }

    public static function subdomainFromHost(?string $host = null): ?string
    {
        $host = self::normalizeHost($host ?? request()->getHost());

        if (self::isCentralHost($host) || ! self::subdomainsEnabled()) {
            return null;
        }

        $domain = self::centralDomain();

        if (! str_ends_with($host, '.'.$domain)) {
            return null;
        }

        $sub = Str::before($host, '.'.$domain);

        if ($sub === '' || str_contains($sub, '.') || in_array($sub, ['www', ...config('coopos.reserved_slugs', [])], true)) {
            return null;
        }

        return $sub;
    }

    public static function tenantFromHost(?string $host = null): ?Tenant
    {
        $slug = self::subdomainFromHost($host);

        if (! $slug) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }

    public static function url(Tenant $tenant, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if (self::subdomainsEnabled()) {
            return self::absolute(self::centralDomain(), $path, $tenant->slug);
        }

        return url('/'.$tenant->slug.($path === '/' ? '/dashboard' : $path));
    }

    public static function centralUrl(string $path = '/'): string
    {
        return self::absolute(self::centralDomain(), '/'.ltrim($path, '/'));
    }

    public static function pathPrefix(?Tenant $tenant = null): string
    {
        $tenant ??= TenantContext::current();

        if (! $tenant) {
            return '';
        }

        if (self::subdomainsEnabled() && self::isTenantHost()) {
            return '';
        }

        return '/'.$tenant->slug;
    }

    public static function registerRoutes(): void
    {
        Route::middleware('web')
            ->prefix('{tenant}')
            ->group(base_path('routes/tenant.php'));

        if (self::subdomainsEnabled()) {
            Route::middleware('web')
                ->domain('{tenant}.'.self::centralDomain())
                ->group(base_path('routes/tenant.php'));
        }
    }

    public static function normalizeHost(?string $host): string
    {
        $host = strtolower((string) $host);

        return Str::before($host, ':');
    }

    private static function absolute(string $domain, string $path, ?string $subdomain = null): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $host = $subdomain ? $subdomain.'.'.$domain : $domain;

        return $scheme.'://'.$host.($port ? ':'.$port : '').$path;
    }
}
