<?php

declare(strict_types=1);

namespace AlgorixPay\Contracts;

interface TailGenerator
{
    public function generate(int $baseTiyin, string $currency, int $attempt): int;

    public function maxSlots(): int;
}
