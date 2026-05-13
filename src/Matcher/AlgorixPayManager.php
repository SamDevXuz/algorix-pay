<?php

declare(strict_types=1);

namespace AlgorixPay\Matcher;

use Illuminate\Contracts\Container\Container;

final class AlgorixPayManager
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function expect(int $sumAmount): PaymentExpectation
    {
        return $this->newExpectation()->expect($sumAmount);
    }

    public function expectTiyin(int $amountTiyin): PaymentExpectation
    {
        return $this->newExpectation()->expectTiyin($amountTiyin);
    }

    private function newExpectation(): PaymentExpectation
    {
        return $this->container->make(PaymentExpectation::class);
    }
}
