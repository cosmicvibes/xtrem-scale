<?php

namespace CosmicVibes\XtremScale\Tests;

use CosmicVibes\XtremScale\XtremScaleServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            XtremScaleServiceProvider::class,
        ];
    }
}
