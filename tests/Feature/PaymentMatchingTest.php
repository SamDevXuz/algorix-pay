<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Feature;

use AlgorixPay\Events\PaymentExpected;
use AlgorixPay\Events\PaymentMatched;
use AlgorixPay\Events\PaymentReceived;
use AlgorixPay\Facades\AlgorixPay;
use AlgorixPay\Matcher\Exceptions\TailExhaustedException;
use AlgorixPay\Support\ParsedPayment;
use AlgorixPay\Support\PendingPayment;
use AlgorixPay\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;

final class PaymentMatchingTest extends TestCase
{
    private function makePayment(int $amountTiyin, string $currency = 'UZS'): ParsedPayment
    {
        return new ParsedPayment(
            source: 'clickuz',
            amountTiyin: $amountTiyin,
            currency: $currency,
            transactionId: 'TX123',
            senderMasked: null,
            receiverMasked: null,
            receivedAt: null,
            rawText: 'test',
        );
    }

    public function test_expect_creates_pending_and_dispatches_event(): void
    {
        Event::fake([PaymentExpected::class]);

        $pending = AlgorixPay::expect(50_000)
            ->currency('UZS')
            ->forOrder('order-123')
            ->expiresInMinutes(15)
            ->create();

        $this->assertGreaterThanOrEqual(5_000_001, $pending->amountTiyin);
        $this->assertLessThanOrEqual(5_000_099, $pending->amountTiyin);
        $this->assertSame(5_000_000, $pending->baseTiyin);
        $this->assertSame('UZS', $pending->currency);
        $this->assertSame(['order_id' => 'order-123'], $pending->meta);
        $this->assertStringEndsWith(" so'm", $pending->humanAmount);

        Event::assertDispatched(PaymentExpected::class, 1);
    }

    public function test_matching_payment_triggers_payment_matched(): void
    {
        Event::fake([PaymentMatched::class]);

        $pending = AlgorixPay::expect(50_000)->currency('UZS')->forOrder('order-1')->create();

        event(new PaymentReceived($this->makePayment($pending->amountTiyin), 'clickuz:1', 'clickuz'));

        Event::assertDispatched(PaymentMatched::class, function (PaymentMatched $e) use ($pending): bool {
            return $e->pending->reference === $pending->reference
                && $e->payment->amountTiyin === $pending->amountTiyin
                && $e->bankMessageId === 'clickuz:1'
                && $e->bankSource === 'clickuz';
        });
    }

    public function test_no_match_when_amount_lacks_tail(): void
    {
        Event::fake([PaymentMatched::class]);

        AlgorixPay::expect(50_000)->currency('UZS')->forOrder('order-1')->create();

        event(new PaymentReceived($this->makePayment(5_000_000), 'clickuz:2', 'clickuz'));

        Event::assertNotDispatched(PaymentMatched::class);
    }

    public function test_replay_does_not_match_twice(): void
    {
        Event::fake([PaymentMatched::class]);

        $pending = AlgorixPay::expect(50_000)->currency('UZS')->forOrder('order-1')->create();

        event(new PaymentReceived($this->makePayment($pending->amountTiyin), 'clickuz:1', 'clickuz'));
        event(new PaymentReceived($this->makePayment($pending->amountTiyin), 'clickuz:1', 'clickuz'));

        Event::assertDispatched(PaymentMatched::class, 1);
    }

    public function test_currency_mismatch_drop_does_not_match(): void
    {
        $this->app['config']->set('algorix-pay.matcher.currency_mismatch_action', 'drop');
        $this->app->forgetInstance(\AlgorixPay\Listeners\MatchPendingPayment::class);

        Event::fake([PaymentMatched::class]);

        $pending = AlgorixPay::expect(50_000)->currency('UZS')->forOrder('order-1')->create();

        event(new PaymentReceived($this->makePayment($pending->amountTiyin, 'USD'), 'clickuz:1', 'clickuz'));

        Event::assertNotDispatched(PaymentMatched::class);
    }

    public function test_currency_mismatch_match_anyway_does_match(): void
    {
        $this->app['config']->set('algorix-pay.matcher.currency_mismatch_action', 'match_anyway');
        $this->app->forgetInstance(\AlgorixPay\Listeners\MatchPendingPayment::class);

        Event::fake([PaymentMatched::class]);

        $pending = AlgorixPay::expect(50_000)->currency('UZS')->forOrder('order-1')->create();

        event(new PaymentReceived($this->makePayment($pending->amountTiyin, 'USD'), 'clickuz:1', 'clickuz'));

        Event::assertDispatched(PaymentMatched::class, 1);
    }

    public function test_exhausting_tail_slots_throws(): void
    {
        $cache = $this->app->make(CacheRepository::class);
        $prefix = $this->app['config']->get('algorix-pay.matcher.key_prefix');

        $stub = new PendingPayment(
            amountTiyin: 0,
            baseTiyin: 0,
            currency: 'UZS',
            humanAmount: '',
            payableType: null,
            payableId: null,
            meta: [],
            expiresAt: '',
            reference: 'stub',
        );

        for ($tail = 1; $tail <= 99; $tail++) {
            $cache->put($prefix.(5_000_000 + $tail), $stub, 60);
        }

        $this->expectException(TailExhaustedException::class);
        AlgorixPay::expect(50_000)->currency('UZS')->create();
    }
}
