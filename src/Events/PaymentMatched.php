<?php

declare(strict_types=1);

namespace AlgorixPay\Events;

use AlgorixPay\Support\ParsedPayment;
use AlgorixPay\Support\PendingPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentMatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PendingPayment $pending,
        public readonly ParsedPayment $payment,
        public readonly string $bankMessageId,
        public readonly string $bankSource,
    ) {
    }
}
