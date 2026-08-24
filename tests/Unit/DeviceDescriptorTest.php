<?php

namespace Tests\Unit;

use App\Support\DeviceDescriptor;
use PHPUnit\Framework\TestCase;

class DeviceDescriptorTest extends TestCase
{
    public function test_it_labels_firefox_on_macos(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:153.0) Gecko/20100101 Firefox/153.0';

        $this->assertSame('Firefox on macOS', DeviceDescriptor::label($ua));
    }

    public function test_it_labels_chrome_on_windows(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $this->assertSame('Chrome on Windows', DeviceDescriptor::label($ua));
    }

    public function test_it_handles_missing_user_agent(): void
    {
        $this->assertSame('Unknown device', DeviceDescriptor::label(null));
    }
}
