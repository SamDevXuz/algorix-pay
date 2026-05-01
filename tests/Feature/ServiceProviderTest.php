<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Feature;

use AlgorixPay\Drivers\ClickDriver;
use AlgorixPay\Services\MadelineService;
use AlgorixPay\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame(12345, config('algorix-pay.api.id'));
        $this->assertSame('test-hash', config('algorix-pay.api.hash'));
        $this->assertSame(ClickDriver::class, config('algorix-pay.drivers.click.class'));
    }

    public function test_madeline_service_is_singleton(): void
    {
        $a = $this->app->make(MadelineService::class);
        $b = $this->app->make(MadelineService::class);

        $this->assertSame($a, $b);
    }

    public function test_throws_when_api_credentials_missing(): void
    {
        config()->set('algorix-pay.api.id', null);
        config()->set('algorix-pay.api.hash', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ALGORIX_API_ID');

        $this->app->make(MadelineService::class);
    }

    public function test_only_enabled_drivers_are_resolved(): void
    {
        config()->set('algorix-pay.drivers.click.enabled', true);
        config()->set('algorix-pay.drivers.payme.enabled', false);
        config()->set('algorix-pay.drivers.uzum.enabled', false);

        $service = $this->app->make(MadelineService::class);

        $reflection = new \ReflectionClass($service);
        $drivers = $reflection->getProperty('drivers')->getValue($service);

        $this->assertArrayHasKey('clickuz', $drivers);
        $this->assertArrayNotHasKey('payme', $drivers);
        $this->assertArrayNotHasKey('uzumbank_bot', $drivers);
        $this->assertInstanceOf(ClickDriver::class, $drivers['clickuz']);
    }

    public function test_invalid_driver_class_is_skipped(): void
    {
        config()->set('algorix-pay.drivers.click.enabled', true);
        config()->set('algorix-pay.drivers.click.class', 'NonExistent\\Class');
        config()->set('algorix-pay.drivers.payme.enabled', false);
        config()->set('algorix-pay.drivers.uzum.enabled', false);

        $service = $this->app->make(MadelineService::class);

        $reflection = new \ReflectionClass($service);
        $drivers = $reflection->getProperty('drivers')->getValue($service);

        $this->assertSame([], $drivers);
    }

    public function test_throws_when_driver_does_not_implement_contract(): void
    {
        config()->set('algorix-pay.drivers.click.enabled', true);
        config()->set('algorix-pay.drivers.click.class', \stdClass::class);
        config()->set('algorix-pay.drivers.payme.enabled', false);
        config()->set('algorix-pay.drivers.uzum.enabled', false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(MadelineService::class);
    }

    public function test_console_command_is_registered(): void
    {
        $commands = $this->app->make('Illuminate\Contracts\Console\Kernel')->all();

        $this->assertArrayHasKey('pay:listen', $commands);
    }
}
