<?php

namespace CosmicVibes\XtremScale\Tests;

use CosmicVibes\XtremScale\XtremScale;

class XtremScaleServiceProviderTest extends TestCase
{
    public function test_it_registers_the_singleton(): void
    {
        $this->assertInstanceOf(XtremScale::class, $this->app->make('xtrem-scale'));
    }

    public function test_config_defaults(): void
    {
        $this->assertSame('192.168.1.100', config('xtrem-scale.ip_address'));
        $this->assertSame(4445, config('xtrem-scale.send_port'));
        $this->assertSame(5556, config('xtrem-scale.receive_port'));
        $this->assertSame(5, config('xtrem-scale.timeout'));
    }

    public function test_it_can_be_constructed_directly(): void
    {
        $scale = new XtremScale('10.0.0.1', 4000, 5000, 3);

        $this->assertInstanceOf(XtremScale::class, $scale);
    }
}
