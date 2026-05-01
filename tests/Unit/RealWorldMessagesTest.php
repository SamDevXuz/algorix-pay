<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Unit;

use AlgorixPay\Drivers\ClickDriver;
use AlgorixPay\Drivers\PaymeDriver;
use AlgorixPay\Drivers\UzumDriver;
use PHPUnit\Framework\TestCase;

final class RealWorldMessagesTest extends TestCase
{
    public function test_click_multiline_emoji_message(): void
    {
        $text = "🔔 Yangi tranzaksiya\n💰 Hisobingizga 125 000 so'm tushdi\n📝 Tranzaksiya: 9876543210\n📍 Karta: 8600****4321";

        $result = (new ClickDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(12_500_000, $result->amountTiyin);
        $this->assertSame('UZS', $result->currency);
        $this->assertSame('9876543210', $result->transactionId);
        $this->assertSame('8600****4321', $result->receiverMasked);
    }

    public function test_click_arrow_notation_extracts_sender_and_receiver(): void
    {
        $text = "Hisobingizga 30 000 so'm tushdi. 8600****1234 → 9860****5678. Tranzaksiya: TX555666";

        $result = (new ClickDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame('8600****1234', $result->senderMasked);
        $this->assertSame('9860****5678', $result->receiverMasked);
    }

    public function test_payme_russian_with_ruble_keeps_uzs_when_uzs_token_present(): void
    {
        $text = "Кошелек пополнен на 50 000 сум. Чек: PME99887";

        $result = (new PaymeDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame('UZS', $result->currency);
    }

    public function test_uzum_emoji_with_multiple_amounts_picks_currency_amount(): void
    {
        $text = "✅ Hisobingizga tushdi\nKarta: 8600****1111\nSumma: 75 000 so'm\nKomissiya: 1500\nChek: U-2026-05-01-XYZ";

        $result = (new UzumDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(7_500_000, $result->amountTiyin);
        $this->assertSame('UZS', $result->currency);
        $this->assertSame('U-2026-05-01-XYZ', $result->transactionId);
    }

    public function test_carriage_return_and_tab_separators(): void
    {
        $text = "Hisobingizga\t10 000 so'm\rtushdi.\r\nTranzaksiya: ABC100100";

        $result = (new ClickDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame(1_000_000, $result->amountTiyin);
        $this->assertSame('ABC100100', $result->transactionId);
    }

    public function test_outgoing_with_emoji_is_ignored(): void
    {
        $text = "❌ 8600****1234 kartangizdan 50 000 so'm yechildi.";

        $this->assertNull((new ClickDriver)->parse($text));
    }

    public function test_payme_arrow_notation_yields_receiver(): void
    {
        $text = "Hamyoningizga 40 000 so'm pul tushdi. 8600****1111 → 9860****2222. Chek: PME-2026-001";

        $result = (new PaymeDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame('8600****1111', $result->senderMasked);
        $this->assertSame('9860****2222', $result->receiverMasked);
        $this->assertSame('PME-2026-001', $result->transactionId);
    }

    public function test_unicode_arrows_are_normalized(): void
    {
        $text = "Hisobingizga 5 000 so'm tushdi. 8600****0001➡9860****0002. Tranzaksiya: ABC500500";

        $result = (new ClickDriver)->parse($text);

        $this->assertNotNull($result);
        $this->assertSame('8600****0001', $result->senderMasked);
        $this->assertSame('9860****0002', $result->receiverMasked);
    }
}
