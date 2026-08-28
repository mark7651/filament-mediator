<?php

namespace Mediator\Tests;

use Mediator\MediatorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MediatorServiceProvider::class];
    }
}
