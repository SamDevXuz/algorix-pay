<?php

declare(strict_types=1);

namespace AlgorixPay;

use AlgorixPay\Console\ListenPaymentsCommand;
use AlgorixPay\Contracts\PaymentDriver;
use AlgorixPay\Contracts\TailGenerator;
use AlgorixPay\Events\PaymentReceived;
use AlgorixPay\Listeners\MatchPendingPayment;
use AlgorixPay\Matcher\AlgorixPayManager;
use AlgorixPay\Matcher\PaymentExpectation;
use AlgorixPay\Services\MadelineService;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
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

            $apiId = (int) $config->get('algorix-pay.api.id');
            $apiHash = (string) $config->get('algorix-pay.api.hash');

            if ($apiId === 0 || $apiHash === '') {
                throw new \RuntimeException('Algorix Pay: ALGORIX_API_ID and ALGORIX_API_HASH must be set.');
            }

            $cache = $this->resolveCacheStore(
                $app,
                $config->get('algorix-pay.dedup.cache_store'),
            );

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

        $this->app->singleton(TailGenerator::class, function ($app): TailGenerator {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            $mode = (string) $config->get('algorix-pay.matcher.tail_mode', 'tiyin');
            $map = (array) $config->get('algorix-pay.matcher.tail_generators', []);
            $class = $map[$mode] ?? null;

            if (! is_string($class) || ! class_exists($class)) {
                throw new \RuntimeException(sprintf(
                    "Algorix Pay: unknown matcher.tail_mode '%s'.",
                    $mode,
                ));
            }

            $instance = $app->make($class);

            if (! $instance instanceof TailGenerator) {
                throw new \RuntimeException(sprintf(
                    'Algorix Pay: tail generator "%s" must implement %s.',
                    $class,
                    TailGenerator::class,
                ));
            }

            return $instance;
        });

        $this->app->bind(PaymentExpectation::class, function ($app): PaymentExpectation {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var LogManager $logs */
            $logs = $app->make('log');

            $cache = $this->resolveCacheStore(
                $app,
                $config->get('algorix-pay.matcher.cache_store'),
            );

            $logChannel = (string) $config->get('algorix-pay.logging.channel', 'stack');

            return new PaymentExpectation(
                tails: $app->make(TailGenerator::class),
                cache: $cache,
                events: $app->make(Dispatcher::class),
                logger: $logs->channel($logChannel),
                keyPrefix: (string) $config->get('algorix-pay.matcher.key_prefix', 'algorix:pending:'),
                defaultTtl: (int) $config->get('algorix-pay.matcher.default_ttl', 900),
                maxAttempts: (int) $config->get('algorix-pay.matcher.tail_max_attempts', 50),
                defaultCurrency: (string) $config->get('algorix-pay.matcher.default_currency', 'UZS'),
            );
        });

        $this->app->singleton(AlgorixPayManager::class, function ($app): AlgorixPayManager {
            return new AlgorixPayManager($app);
        });

        $this->app->bind(MatchPendingPayment::class, function ($app): MatchPendingPayment {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var LogManager $logs */
            $logs = $app->make('log');

            $cache = $this->resolveCacheStore(
                $app,
                $config->get('algorix-pay.matcher.cache_store'),
            );

            $logChannel = (string) $config->get('algorix-pay.logging.channel', 'stack');

            return new MatchPendingPayment(
                cache: $cache,
                events: $app->make(Dispatcher::class),
                logger: $logs->channel($logChannel),
                keyPrefix: (string) $config->get('algorix-pay.matcher.key_prefix', 'algorix:pending:'),
                currencyMismatchAction: (string) $config->get('algorix-pay.matcher.currency_mismatch_action', 'log'),
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

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        if ((bool) $config->get('algorix-pay.matcher.enabled', true)) {
            /** @var Dispatcher $events */
            $events = $this->app->make(Dispatcher::class);
            $events->listen(PaymentReceived::class, [MatchPendingPayment::class, 'handle']);
        }
    }

    private function resolveCacheStore(Container $app, mixed $storeName): CacheRepository
    {
        /** @var CacheFactory $cacheFactory */
        $cacheFactory = $app->make(CacheFactory::class);

        return $storeName !== null
            ? $cacheFactory->store((string) $storeName)
            : $cacheFactory->store();
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
