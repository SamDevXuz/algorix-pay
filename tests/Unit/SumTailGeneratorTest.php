<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Matcher\Tail\SumTailGenerator;
use PHPUnit\Framework\TestCase;

final class SumTailGeneratorTest extends TestCase
{
    public function test_max_slots_is_999(): void
    {
        $this->assertSame(999, (new SumTailGenerator)->maxSlots());
    }

    public function test_output_is_within_base_plus_100_to_99900_step_100(): void
    {
        $gen = new SumTailGenerator;
        $base = 5_000_000;

        for ($i = 0; $i < $gen->maxSlots(); $i++) {
            $value = $gen->generate($base, 'UZS', $i);
            $this->assertGreaterThanOrEqual($base + 100, $value);
            $this->assertLessThanOrEqual($base + 99_900, $value);
            $this->assertSame(0, ($value - $base) % 100);
        }
    }

    public function test_distinct_attempts_produce_distinct_outputs(): void
    {
        $gen = new SumTailGenerator;
        $base = 5_000_000;

        $seen = [];
        for ($i = 0; $i < $gen->maxSlots(); $i++) {
            $seen[] = $gen->generate($base, 'UZS', $i);
        }

        $this->assertCount(999, array_unique($seen));
    }

    public function test_attempt_out_of_range_throws(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new SumTailGenerator)->generate(5_000_000, 'UZS', 999);
    }
}
