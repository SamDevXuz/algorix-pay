<?php

declare(strict_types=1);

namespace AlgorixPay\Drivers;

final class UzumDriver extends AbstractRegexDriver
{
    public function __construct(string $source = 'uzumbank_bot')
    {
        parent::__construct($source);
    }

    protected function incomingMarkers(): array
    {
        return [
            'hisobingizga',
            'kirim',
            'tushdi',
            'kelib tushdi',
            'qabul qilindi',
            'tushum',
            'зачислено',
            'поступил',
            'пополнение',
            'incoming',
            'received',
        ];
    }

    protected function amountPatterns(): array
    {
        return [
            '/\+\s*([0-9][0-9 .,]*)\s*(?:so\'?m|sum|сум|UZS)/iu',
            '/([0-9][0-9 .,]*)\s*(?:so\'?m|sum|сум|UZS)/iu',
            '/(?:summa|сумма|amount|miqdor)[^0-9]{0,12}([0-9][0-9 .,]*)/iu',
        ];
    }

    protected function transactionPatterns(): array
    {
        return [
            '/(?:chek|check|чек|receipt)[^\dA-Za-z]{0,8}([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
            '/(?:tranzaksiya|транзак[цс]ия|transaction|trn)[^\dA-Za-z]{0,8}([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
            '/(?:id|№|#)[^\dA-Za-z]{0,4}(\d{6,})/iu',
        ];
    }
}
