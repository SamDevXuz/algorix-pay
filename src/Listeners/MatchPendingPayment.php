<?php

declare(strict_types=1);

namespace AlgorixPay\Listeners;

use AlgorixPay\Events\PaymentMatched;
use AlgorixPay\Events\PaymentReceived;
use AlgorixPay\Support\PendingPayment;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

final class MatchPendingPayment
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
        private readonly string $keyPrefix,
        private readonly string $currencyMismatchAction,
    ) {
    }

    public function handle(PaymentReceived $event): void
    {
        $key = $this->keyPrefix.$event->payment->amountTiyin;
        $pending = $this->cache->pull($key);

        if (! $pending instanceof PendingPayment) {
            return;
        }

        if ($pending->currency !== $event->payment->currency) {
            $context = [
                'reference' => $pending->reference,
                'amount_tiyin' => $pending->amountTiyin,
                'expected_currency' => $pending->currency,
                'received_currency' => $event->payment->currency,
                'bank_message_id' => $event->bankMessageId,
            ];

            switch ($this->currencyMismatchAction) {
                case 'match_anyway':
                    $this->logger->warning('algorix.matcher.currency_mismatch_match', $context);
                    break;
                case 'drop':
                    $this->logger->warning('algorix.matcher.currency_mismatch_drop', $context);

                    return;
                case 'log':
                default:
                    $this->logger->warning('algorix.matcher.currency_mismatch', $context);

                    return;
            }
        }

        $this->logger->info('algorix.matcher.matched', [
            'reference' => $pending->reference,
            'amount_tiyin' => $pending->amountTiyin,
            'currency' => $pending->currency,
            'bank_message_id' => $event->bankMessageId,
            'bank_source' => $event->bankSource,
        ]);

        $this->events->dispatch(new PaymentMatched(
            pending: $pending,
            payment: $event->payment,
            bankMessageId: $event->bankMessageId,
            bankSource: $event->bankSource,
        ));
    }
}
