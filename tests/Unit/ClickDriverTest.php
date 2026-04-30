<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Drivers\ClickDriver;
use PHPUnit\Framework\TestCase;

final class ClickDriverTest extends TestCase
{
    public function test_parses_uzbek_incoming_with_spaced_amount(): void
    {
        $driver = new ClickDriver;

        $text = "Hisobingizga 15 432 so'm tushdi. Karta: 8600****1234. Tranzaksiya: 987654321";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(1543200, $result->amountTiyin);
        $this->assertSame('UZS', $result->currency);
        $this->assertSame('987654321', $result->transactionId);
        $this->assertSame('clickuz', $result->source);
    }

    public function test_parses_russian_credited_message(): void
    {
        $driver = new ClickDriver;

        $text = 'На счёт зачислен 50 000 сум. Транзакция: ABC123456';

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(5_000_000, $result->amountTiyin);
        $this->assertSame('ABC123456', $result->transactionId);
    }

    public function test_returns_null_for_outgoing_message(): void
    {
        $driver = new ClickDriver;

        $text = "8600****1234 kartangizdan 10 000 so'm yechildi.";

        $this->assertNull($driver->parse($text));
    }

    public function test_returns_null_for_unrelated_text(): void
    {
        $driver = new ClickDriver;

        $this->assertNull($driver->parse('Salom, qalaysiz?'));
    }

    public function test_handles_decimal_with_dot(): void
    {
        $driver = new ClickDriver;

        $text = "Hisobingizga 1234.56 so'm tushdi";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(123456, $result->amountTiyin);
    }

    public function test_handles_thousand_dot_separator(): void
    {
        $driver = new ClickDriver;

        $text = "Hisobingizga 1.234.567 so'm tushdi";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(123_456_700, $result->amountTiyin);
    }

    public function test_normalises_nbsp(): void
    {
        $driver = new ClickDriver;

        $text = "Hisobingizga 25\u{00A0}000 so'm tushdi";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(2_500_000, $result->amountTiyin);
    }
}
