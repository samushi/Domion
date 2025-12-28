<?php

namespace Samushi\Domion\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Samushi\Domion\Providers\DddArchitectServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            DddArchitectServiceProvider::class,
            \Samushi\QueryFilter\QueryFilterServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
