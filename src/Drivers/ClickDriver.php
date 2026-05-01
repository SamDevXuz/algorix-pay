<?php

declare(strict_types=1);

namespace AlgorixPay\Drivers;

final class ClickDriver extends AbstractRegexDriver
{
    public function __construct(string $source = 'clickuz')
    {
        parent::__construct($source);
    }

    protected function incomingMarkers(): array
    {
        return [
            'hisobingizga',
            'tushdi',
            'qabul qilindi',
            'pополнен',
            'зачислен',
            'поступил',
            'received',
            'credited',
        ];
    }

    protected function amountPatterns(): array
    {
        return [
            '/([0-9][0-9 .,]*)\s*(?:so\'?m|so\x{2018}m|sum|сум|UZS)\b/iu',
            '/(?:summa|amount|miqdor)[^0-9]{0,12}([0-9][0-9 .,]*)/iu',
        ];
    }

    protected function transactionPatterns(): array
    {
        return [
            '/(?:tranzaksiya|транзак[цс]ия|transaction|trn|chek|check)\s*[:#№]\s*([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
            '/\b(?:id|n)\s*[:#№]\s*(\d{6,})/iu',
        ];
    }
}
