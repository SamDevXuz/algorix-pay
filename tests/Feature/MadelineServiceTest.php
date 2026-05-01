<?php

declare(strict_types=1);

namespace AlgorixPay\Tests\Feature;

use AlgorixPay\Drivers\ClickDriver;
use AlgorixPay\Events\PaymentReceived;
use AlgorixPay\Services\MadelineService;
use AlgorixPay\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Events\Dispatcher;
use Psr\Log\NullLogger;

final class MadelineServiceTest extends TestCase
{
    private Dispatcher $events;

    /** @var list<PaymentReceived> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->events = new Dispatcher;
        $this->captured = [];
        $this->events->listen(PaymentReceived::class, function (PaymentReceived $event): void {
            $this->captured[] = $event;
        });
    }

    private function makeService(?string $resolvedUsername): MadelineService
    {
        return new class(
            ['clickuz' => new ClickDriver('clickuz')],
            $this->events,
            new NullLogger,
            new Repository(new ArrayStore),
            sys_get_temp_dir().'/algorix-pay-test/userbot.madeline',
            12345,
            'test-hash',
            10,
            $resolvedUsername,
        ) extends MadelineService {
            public function __construct(
                array $drivers,
                Dispatcher $events,
                NullLogger $logger,
                Repository $cache,
                string $sessionPath,
                int $apiId,
                string $apiHash,
                int $dedupTtlSeconds,
                private readonly ?string $stubUsername,
            ) {
                parent::__construct($drivers, $events, $logger, $cache, $sessionPath, $apiId, $apiHash, $dedupTtlSeconds);
            }

            protected function extractPeerUsername(array $message): ?string
            {
                return $this->stubUsername;
            }
        };
    }

    private function newMessageUpdate(string $text, int $id = 1): array
    {
        return [
            '_' => 'updateNewMessage',
            'message' => [
                '_' => 'message',
                'id' => $id,
                'message' => $text,
                'peer_id' => ['_' => 'peerUser', 'user_id' => 999],
            ],
        ];
    }

    public function test_dispatches_event_on_valid_payment(): void
    {
        $service = $this->makeService('clickuz');

        $service->processUpdates([
            $this->newMessageUpdate("Hisobingizga 50 000 so'm tushdi. Tranzaksiya: ABC123456"),
        ]);

        $this->assertCount(1, $this->captured);
        $event = $this->captured[0];
        $this->assertSame('clickuz', $event->bankSource);
        $this->assertSame(5_000_000, $event->payment->amountTiyin);
        $this->assertSame('ABC123456', $event->payment->transactionId);
        $this->assertSame('clickuz:1', $event->bankMessageId);
    }

    public function test_dedups_repeated_message_id(): void
    {
        $service = $this->makeService('clickuz');
        $update = $this->newMessageUpdate("Hisobingizga 50 000 so'm tushdi. Tranzaksiya: ABC123456", 42);

        $service->processUpdates([$update, $update, $update]);

        $this->assertCount(1, $this->captured);
    }

    public function test_ignores_unknown_source(): void
    {
        $service = $this->makeService('random_bot');

        $service->processUpdates([
            $this->newMessageUpdate("Hisobingizga 50 000 so'm tushdi. Tranzaksiya: ABC1"),
        ]);

        $this->assertSame([], $this->captured);
    }

    public function test_skips_unparseable_text(): void
    {
        $service = $this->makeService('clickuz');

        $service->processUpdates([
            $this->newMessageUpdate('Marketing xabari, hech qanday tranzaksiya yo\'q.'),
        ]);

        $this->assertSame([], $this->captured);
    }

    public function test_skips_when_peer_username_is_null(): void
    {
        $service = $this->makeService(null);

        $service->processUpdates([
            $this->newMessageUpdate("Hisobingizga 50 000 so'm tushdi. Tranzaksiya: ABC1"),
        ]);

        $this->assertSame([], $this->captured);
    }

    public function test_skips_non_message_update(): void
    {
        $service = $this->makeService('clickuz');

        $service->processUpdates([
            ['_' => 'updateUserStatus', 'user_id' => 1],
            ['_' => 'updateNewMessage', 'message' => ['_' => 'messageService', 'id' => 1]],
        ]);

        $this->assertSame([], $this->captured);
    }

    public function test_skips_empty_message_text(): void
    {
        $service = $this->makeService('clickuz');

        $service->processUpdates([
            $this->newMessageUpdate('   '),
            $this->newMessageUpdate(''),
        ]);

        $this->assertSame([], $this->captured);
    }

    public function test_two_distinct_messages_dispatch_two_events(): void
    {
        $service = $this->makeService('clickuz');

        $service->processUpdates([
            $this->newMessageUpdate("Hisobingizga 10 000 so'm tushdi. Tranzaksiya: ABC111111", 1),
            $this->newMessageUpdate("Hisobingizga 20 000 so'm tushdi. Tranzaksiya: ABC222222", 2),
        ]);

        $this->assertCount(2, $this->captured);
        $this->assertSame(1_000_000, $this->captured[0]->payment->amountTiyin);
        $this->assertSame(2_000_000, $this->captured[1]->payment->amountTiyin);
    }
}
