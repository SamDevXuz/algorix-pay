<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Drivers\UzumDriver;
use PHPUnit\Framework\TestCase;

final class UzumDriverTest extends TestCase
{
    public function test_parses_uzum_incoming(): void
    {
        $driver = new UzumDriver;

        $text = "Hisobingizga +75 500 so'm tushdi. Tranzaksiya: UZ778899. Karta: 8600****4321";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(7_550_000, $result->amountTiyin);
        $this->assertSame('UZ778899', $result->transactionId);
        $this->assertSame('uzumbank_bot', $result->source);
        $this->assertSame('8600****4321', $result->receiverMasked);
    }

    public function test_parses_russian_credited(): void
    {
        $driver = new UzumDriver;

        $text = 'На счет зачислено 200 000 сум. Чек: U-AB-99X';

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(20_000_000, $result->amountTiyin);
        $this->assertSame('U-AB-99X', $result->transactionId);
    }

    public function test_ignores_outgoing_message(): void
    {
        $driver = new UzumDriver;

        $this->assertNull($driver->parse("Kartangizdan 30 000 so'm yechildi."));
    }
}
