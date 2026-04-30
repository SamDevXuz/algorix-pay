<?php

declare(strict_types=1);

namespace AlgorixPay\Contracts;

use AlgorixPay\Support\ParsedPayment;

interface PaymentDriver
{
    public function source(): string;

    public function parse(string $text): ?ParsedPayment;
}
