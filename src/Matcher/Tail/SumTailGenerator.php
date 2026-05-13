<?php

declare(strict_types=1);

namespace AlgorixPay\Matcher\Tail;

use AlgorixPay\Contracts\TailGenerator;

final class SumTailGenerator implements TailGenerator
{
    /** @var list<int> */
    private array $shuffled;

    public function __construct()
    {
        $tails = range(1, 999);
        shuffle($tails);
        $this->shuffled = $tails;
    }

    public function generate(int $baseTiyin, string $currency, int $attempt): int
    {
        if ($attempt < 0 || $attempt >= $this->maxSlots()) {
            throw new \OutOfRangeException(sprintf(
                'SumTailGenerator: attempt %d out of range [0, %d).',
                $attempt,
                $this->maxSlots(),
            ));
        }

        return $baseTiyin + $this->shuffled[$attempt] * 100;
    }

    public function maxSlots(): int
    {
        return 999;
    }
}
