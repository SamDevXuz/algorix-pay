<?php

declare(strict_types=1);

namespace AlgorixPay\Tests;

use AlgorixPay\AlgorixPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AlgorixPayServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('algorix-pay.api.id', 12345);
        $app['config']->set('algorix-pay.api.hash', 'test-hash');
        $app['config']->set('algorix-pay.session_path', sys_get_temp_dir().'/algorix-pay-test/userbot.madeline');
        $app['config']->set('algorix-pay.dedup.ttl_seconds', 10);
        $app['config']->set('cache.default', 'array');
    }
}
