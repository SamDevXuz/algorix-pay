<?php

declare(strict_types=1);

namespace AlgorixPay\Services;

use AlgorixPay\Contracts\PaymentDriver;
use AlgorixPay\Events\PaymentReceived;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

class MadelineService
{
    private ?API $api = null;

    /**
     * @param  array<string, PaymentDriver>  $drivers  keyed by lower-case username (without @)
     */
    public function __construct(
        private readonly array $drivers,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
        private readonly CacheRepository $cache,
        private readonly string $sessionPath,
        private readonly int $apiId,
        private readonly string $apiHash,
        private readonly int $dedupTtlSeconds = 10,
    ) {
    }

    public function start(): void
    {
        $this->ensureSessionDirectory();

        $settings = (new Settings)
            ->setAppInfo(
                (new AppInfo)
                    ->setApiId($this->apiId)
                    ->setApiHash($this->apiHash)
            );

        $this->api = new API($this->sessionPath, $settings);

        $this->api->start();

        $this->logger->info('algorix.madeline.started', [
            'session' => $this->sessionPath,
            'sources' => array_keys($this->drivers),
        ]);

        $this->loop();
    }

    public function stop(): void
    {
        $this->api?->stop();
    }

    private function loop(): void
    {
        if ($this->api === null) {
            return;
        }

        while (true) {
            $updates = $this->api->getUpdates(['timeout' => 30]);
            $this->processUpdates($updates);
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $updates
     */
    public function processUpdates(iterable $updates): void
    {
        foreach ($updates as $update) {
            $this->handleUpdate($update);
        }
    }

    /**
     * @param  array<string, mixed>  $update
     */
    protected function handleUpdate(array $update): void
    {
        $message = $this->extractMessage($update);
        if ($message === null) {
            return;
        }

        $username = $this->extractPeerUsername($message);
        if ($username === null) {
            return;
        }

        $driver = $this->drivers[$username] ?? null;
        if ($driver === null) {
            return;
        }

        $text = (string) ($message['message'] ?? '');
        if (trim($text) === '') {
            return;
        }

        $messageId = (int) ($message['id'] ?? 0);
        $bankMessageId = sprintf('%s:%d', $username, $messageId);

        if ($this->seen($bankMessageId)) {
            return;
        }

        $parsed = $driver->parse($text);
        if ($parsed === null) {
            $this->logger->debug('algorix.parser.skip', ['source' => $username]);

            return;
        }

        if ($parsed->transactionId !== null && $this->seen('txn:'.$username.':'.$parsed->transactionId)) {
            $this->logger->debug('algorix.payment.dedup_txn', [
                'source' => $username,
                'transaction_id' => $parsed->transactionId,
            ]);

            return;
        }

        $messageDate = $message['date'] ?? null;
        if (is_int($messageDate) && $messageDate > 0) {
            $parsed = $parsed->withReceivedAt(gmdate('Y-m-d\TH:i:s\Z', $messageDate));
        }

        $this->logger->info('algorix.payment.detected', [
            'source' => $username,
            'amount_tiyin' => $parsed->amountTiyin,
            'currency' => $parsed->currency,
            'transaction_id' => $parsed->transactionId,
        ]);

        $this->events->dispatch(new PaymentReceived(
            payment: $parsed,
            bankMessageId: $bankMessageId,
            bankSource: $username,
        ));
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>|null
     */
    protected function extractMessage(array $update): ?array
    {
        $type = $update['_'] ?? null;

        if (in_array($type, ['updateNewMessage', 'updateNewChannelMessage'], true)) {
            $message = $update['message'] ?? null;
            if (is_array($message) && ($message['_'] ?? null) === 'message') {
                return $message;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPeerUsername(array $message): ?string
    {
        if ($this->api === null) {
            return null;
        }

        $peer = $message['peer_id'] ?? $message['from_id'] ?? null;
        if ($peer === null) {
            return null;
        }

        try {
            $info = $this->api->getInfo($peer);
        } catch (\Throwable $e) {
            $this->logger->warning('algorix.peer.lookup_failed', ['error' => $e->getMessage()]);

            return null;
        }

        $username = $info['User']['username'] ?? $info['Chat']['username'] ?? null;
        if (! is_string($username) || $username === '') {
            return null;
        }

        return strtolower($username);
    }

    private function seen(string $bankMessageId): bool
    {
        $key = 'algorix:dedup:'.$bankMessageId;

        if ($this->cache->has($key)) {
            return true;
        }

        $this->cache->put($key, 1, $this->dedupTtlSeconds);

        return false;
    }

    private function ensureSessionDirectory(): void
    {
        $dir = dirname($this->sessionPath);

        if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(sprintf('Algorix session directory not writable: %s', $dir));
        }
    }
}
