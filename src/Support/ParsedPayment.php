<?php

declare(strict_types=1);

namespace AlgorixPay\Support;

final class ParsedPayment
{
    public function __construct(
        public readonly string $source,
        public readonly int $amountTiyin,
        public readonly string $currency,
        public readonly ?string $transactionId,
        public readonly ?string $senderMasked,
        public readonly ?string $receiverMasked,
        public readonly ?string $receivedAt,
        public readonly string $rawText,
    ) {
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'amount_tiyin' => $this->amountTiyin,
            'currency' => $this->currency,
            'transaction_id' => $this->transactionId,
            'sender_masked' => $this->senderMasked,
            'receiver_masked' => $this->receiverMasked,
            'received_at' => $this->receivedAt,
            'raw_text' => $this->rawText,
        ];
    }
}
