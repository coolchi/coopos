<?php

namespace Tests\Unit;

use App\Support\Tenancy;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    public function test_it_extracts_a_cooperative_subdomain(): void
    {
        config([
            'coopos.tenancy.subdomains' => true,
            'coopos.tenancy.central_domain' => 'coopos.test',
        ]);

        $this->assertSame('barakah', Tenancy::subdomainFromHost('barakah.coopos.test'));
        $this->assertNull(Tenancy::subdomainFromHost('coopos.test'));
        $this->assertNull(Tenancy::subdomainFromHost('www.coopos.test'));
        $this->assertNull(Tenancy::subdomainFromHost('127.0.0.1'));
        $this->assertTrue(Tenancy::isNamedCentralHost('coopos.test'));
    }

    public function test_tenant_routes_can_be_cached_when_subdomains_are_enabled(): void
    {
        $artisan = base_path('artisan');

        try {
            $cache = \Illuminate\Support\Facades\Process::env([
                'TENANCY_SUBDOMAINS' => 'true',
                'CENTRAL_DOMAIN' => 'coopos.test',
                'APP_URL' => 'https://coopos.test',
            ])->run([PHP_BINARY, $artisan, 'route:cache']);

            $this->assertTrue($cache->successful(), $cache->errorOutput()."\n".$cache->output());
        } finally {
            \Illuminate\Support\Facades\Process::run([PHP_BINARY, $artisan, 'route:clear']);
        }
    }
}
