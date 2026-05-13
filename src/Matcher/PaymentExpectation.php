<?php

declare(strict_types=1);

namespace AlgorixPay\Matcher;

use AlgorixPay\Contracts\TailGenerator;
use AlgorixPay\Events\PaymentExpected;
use AlgorixPay\Matcher\Exceptions\TailExhaustedException;
use AlgorixPay\Support\PendingPayment;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

final class PaymentExpectation
{
    private ?int $baseTiyin = null;

    private string $currency;

    private int $ttl;

    private ?string $payableType = null;

    private ?string $payableId = null;

    private array $meta = [];

    public function __construct(
        private readonly TailGenerator $tails,
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
        private readonly string $keyPrefix,
        private readonly int $defaultTtl,
        private readonly int $maxAttempts,
        private readonly string $defaultCurrency,
    ) {
        $this->currency = $this->defaultCurrency;
        $this->ttl = $this->defaultTtl;
    }

    public function expect(int $sumAmount): self
    {
        if ($sumAmount <= 0) {
            throw new \InvalidArgumentException('Expected amount must be positive.');
        }

        $this->baseTiyin = $sumAmount * 100;

        return $this;
    }

    public function expectTiyin(int $amountTiyin): self
    {
        if ($amountTiyin <= 0) {
            throw new \InvalidArgumentException('Expected amount (tiyin) must be positive.');
        }

        $this->baseTiyin = $amountTiyin;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function forOrder(Model|string|array|null $payable): self
    {
        if ($payable === null) {
            return $this;
        }

        if ($payable instanceof Model) {
            $this->payableType = $payable->getMorphClass();
            $this->payableId = (string) $payable->getKey();

            return $this;
        }

        if (is_string($payable)) {
            $this->meta['order_id'] = $payable;

            return $this;
        }

        $this->meta = array_merge($this->meta, $payable);

        return $this;
    }

    public function expiresIn(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('TTL must be positive.');
        }

        $this->ttl = $seconds;

        return $this;
    }

    public function expiresInMinutes(int $minutes): self
    {
        return $this->expiresIn($minutes * 60);
    }

    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function create(): PendingPayment
    {
        if ($this->baseTiyin === null) {
            throw new \LogicException('PaymentExpectation::create() called before expect().');
        }

        $base = $this->baseTiyin;
        $currency = $this->currency;
        $ttl = $this->ttl;
        $maxAttempts = min($this->maxAttempts, $this->tails->maxSlots());

        $amountTiyin = null;
        $key = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = $this->tails->generate($base, $currency, $attempt);
            $candidateKey = $this->keyPrefix.$candidate;

            if (! $this->cache->has($candidateKey)) {
                $amountTiyin = $candidate;
                $key = $candidateKey;
                break;
            }
        }

        if ($amountTiyin === null || $key === null) {
            throw TailExhaustedException::forBase($base, $maxAttempts);
        }

        $pending = new PendingPayment(
            amountTiyin: $amountTiyin,
            baseTiyin: $base,
            currency: $currency,
            humanAmount: PendingPayment::format($amountTiyin, $currency),
            payableType: $this->payableType,
            payableId: $this->payableId,
            meta: $this->meta,
            expiresAt: gmdate('Y-m-d\TH:i:s\Z', time() + $ttl),
            reference: bin2hex(random_bytes(6)),
        );

        $this->cache->put($key, $pending, $ttl);

        $this->logger->info('algorix.matcher.expected', [
            'reference' => $pending->reference,
            'amount_tiyin' => $pending->amountTiyin,
            'base_tiyin' => $pending->baseTiyin,
            'currency' => $pending->currency,
            'payable_type' => $pending->payableType,
            'payable_id' => $pending->payableId,
            'expires_at' => $pending->expiresAt,
        ]);

        $this->events->dispatch(new PaymentExpected($pending));

        return $pending;
    }
}
