<?php

declare(strict_types=1);

namespace AlgorixPay;

use AlgorixPay\Console\ListenPaymentsCommand;
use AlgorixPay\Contracts\PaymentDriver;
use AlgorixPay\Services\MadelineService;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;

final class AlgorixPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/algorix-pay.php', 'algorix-pay');

        $this->app->singleton(MadelineService::class, function ($app): MadelineService {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var Dispatcher $events */
            $events = $app->make(Dispatcher::class);
            /** @var LogManager $logs */
            $logs = $app->make('log');
            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);

            $apiId = (int) $config->get('algorix-pay.api.id');
            $apiHash = (string) $config->get('algorix-pay.api.hash');

            if ($apiId === 0 || $apiHash === '') {
                throw new \RuntimeException('Algorix Pay: ALGORIX_API_ID and ALGORIX_API_HASH must be set.');
            }

            $cacheStore = $config->get('algorix-pay.dedup.cache_store');
            $cache = $cacheStore !== null
                ? $cacheFactory->store((string) $cacheStore)
                : $cacheFactory->store();

            $logChannel = (string) $config->get('algorix-pay.logging.channel', 'stack');

            return new MadelineService(
                drivers: $this->resolveDrivers($app, $config),
                events: $events,
                logger: $logs->channel($logChannel),
                cache: $cache,
                sessionPath: (string) $config->get('algorix-pay.session_path'),
                apiId: $apiId,
                apiHash: $apiHash,
                dedupTtlSeconds: (int) $config->get('algorix-pay.dedup.ttl_seconds', 10),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/algorix-pay.php' => config_path('algorix-pay.php'),
            ], 'algorix-pay-config');

            $this->commands([
                ListenPaymentsCommand::class,
            ]);
        }
    }

    /**
     * @return array<string, PaymentDriver>
     */
    private function resolveDrivers($app, ConfigRepository $config): array
    {
        $drivers = [];

        /** @var array<string, array<string, mixed>> $configured */
        $configured = (array) $config->get('algorix-pay.drivers', []);

        foreach ($configured as $key => $entry) {
            if (! ($entry['enabled'] ?? false)) {
                continue;
            }

            $class = $entry['class'] ?? null;
            $source = strtolower((string) ($entry['source'] ?? $key));

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $instance = $app->make($class, ['source' => $source]);

            if (! $instance instanceof PaymentDriver) {
                throw new \RuntimeException(sprintf(
                    'Algorix Pay driver "%s" must implement %s.',
                    $class,
                    PaymentDriver::class,
                ));
            }

            $drivers[$source] = $instance;
        }

        return $drivers;
    }
}
