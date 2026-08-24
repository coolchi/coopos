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
}
