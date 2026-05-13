<?php

declare(strict_types=1);

namespace AlgorixPay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AlgorixPay\Matcher\PaymentExpectation expect(int $sumAmount)
 * @method static \AlgorixPay\Matcher\PaymentExpectation expectTiyin(int $amountTiyin)
 *
 * @see \AlgorixPay\Matcher\AlgorixPayManager
 */
final class AlgorixPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AlgorixPay\Matcher\AlgorixPayManager::class;
    }
}
