<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Matcher\Tail\TiyinTailGenerator;
use PHPUnit\Framework\TestCase;

final class TiyinTailGeneratorTest extends TestCase
{
    public function test_max_slots_is_99(): void
    {
        $this->assertSame(99, (new TiyinTailGenerator)->maxSlots());
    }

    public function test_output_is_within_base_plus_1_to_99(): void
    {
        $gen = new TiyinTailGenerator;
        $base = 5_000_000;

        for ($i = 0; $i < $gen->maxSlots(); $i++) {
            $value = $gen->generate($base, 'UZS', $i);
            $this->assertGreaterThanOrEqual($base + 1, $value);
            $this->assertLessThanOrEqual($base + 99, $value);
        }
    }

    public function test_distinct_attempts_produce_distinct_outputs(): void
    {
        $gen = new TiyinTailGenerator;
        $base = 5_000_000;

        $seen = [];
        for ($i = 0; $i < $gen->maxSlots(); $i++) {
            $seen[] = $gen->generate($base, 'UZS', $i);
        }

        $this->assertCount(99, array_unique($seen));
    }

    public function test_attempt_out_of_range_throws(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new TiyinTailGenerator)->generate(5_000_000, 'UZS', 99);
    }
}
