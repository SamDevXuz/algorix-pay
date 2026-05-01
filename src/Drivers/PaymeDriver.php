<?php

declare(strict_types=1);

namespace AlgorixPay\Drivers;

final class PaymeDriver extends AbstractRegexDriver
{
    public function __construct(string $source = 'payme')
    {
        parent::__construct($source);
    }

    protected function incomingMarkers(): array
    {
        return [
            'pul tushdi',
            'kirim',
            'kirib keldi',
            'qabul qilindi',
            'hamyoningizga',
            'поступление',
            'пополнение',
            'кошелек пополнен',
            'incoming',
            'top-up',
        ];
    }

    protected function amountPatterns(): array
    {
        return [
            '/\+\s*([0-9][0-9 .,]*)\s*(?:so\'?m|sum|сум|UZS)\b/iu',
            '/([0-9][0-9 .,]*)\s*(?:so\'?m|sum|сум|UZS)\b/iu',
            '/(?:summa|сумма|amount)[^0-9]{0,12}([0-9][0-9 .,]*)/iu',
        ];
    }

    protected function transactionPatterns(): array
    {
        return [
            '/(?:chek|check|чек|receipt)\s*[:#№]\s*([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
            '/(?:tranzaksiya|транзак[цс]ия|transaction)\s*[:#№]\s*([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
            '/(?:id|№|#)\s*[:-]?\s*(\d{6,})/iu',
        ];
    }
}
