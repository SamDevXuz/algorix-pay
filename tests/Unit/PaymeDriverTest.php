<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Drivers\PaymeDriver;
use PHPUnit\Framework\TestCase;

final class PaymeDriverTest extends TestCase
{
    public function test_parses_topup_with_plus_amount(): void
    {
        $driver = new PaymeDriver;

        $text = "Hamyoningizga +25 000 so'm pul tushdi. Chek: PME12345";

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(2_500_000, $result->amountTiyin);
        $this->assertSame('PME12345', $result->transactionId);
        $this->assertSame('payme', $result->source);
    }

    public function test_parses_russian_topup(): void
    {
        $driver = new PaymeDriver;

        $text = 'Кошелек пополнен на 100 000 сум. Транзакция: TX9988776';

        $result = $driver->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(10_000_000, $result->amountTiyin);
        $this->assertSame('TX9988776', $result->transactionId);
    }

    public function test_ignores_outgoing(): void
    {
        $driver = new PaymeDriver;

        $this->assertNull($driver->parse("Hamyondan 50 000 so'm yechildi."));
    }

    public function test_ignores_unrelated(): void
    {
        $this->assertNull((new PaymeDriver)->parse('Marketing xabari, chegirma!'));
    }
}
