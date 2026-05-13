<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Feature;

use AlgorixPay\Facades\AlgorixPay;
use AlgorixPay\Matcher\PaymentExpectation;
use AlgorixPay\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class FacadeTest extends TestCase
{
    public function test_expect_returns_payment_expectation(): void
    {
        $this->assertInstanceOf(PaymentExpectation::class, AlgorixPay::expect(1000));
    }

    public function test_successive_calls_return_distinct_instances(): void
    {
        $a = AlgorixPay::expect(1000);
        $b = AlgorixPay::expect(2000);

        $this->assertNotSame($a, $b);
    }

    public function test_create_writes_to_cache_with_configured_prefix(): void
    {
        $pending = AlgorixPay::expect(50_000)->currency('UZS')->forOrder('o-1')->create();

        $cache = $this->app->make(CacheRepository::class);
        $prefix = $this->app['config']->get('algorix-pay.matcher.key_prefix');

        $this->assertTrue($cache->has($prefix.$pending->amountTiyin));
    }
}
