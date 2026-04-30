<?php

declare(strict_types=1);

namespace AlgorixPay\Console;

use AlgorixPay\Services\MadelineService;
use Illuminate\Console\Command;

final class ListenPaymentsCommand extends Command
{
    protected $signature = 'pay:listen';

    protected $description = 'Start the Algorix Pay userbot listener (MTProto). Emits PaymentReceived events.';

    public function handle(MadelineService $service): int
    {
        $this->info('Algorix Pay listener starting...');

        try {
            $service->start();
        } catch (\Throwable $e) {
            $this->error('Listener crashed: '.$e->getMessage());

            report($e);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
