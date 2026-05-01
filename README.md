# Algorix Pay

[![tests](https://github.com/SamDevXuz/algorix-pay/actions/workflows/tests.yml/badge.svg)](https://github.com/SamDevXuz/algorix-pay/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/samdevxuz/algorix-pay.svg)](https://packagist.org/packages/samdevxuz/algorix-pay)
[![PHP Version](https://img.shields.io/packagist/php-v/samdevxuz/algorix-pay.svg)](https://packagist.org/packages/samdevxuz/algorix-pay)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Userbot-based P2P payment receiver for Laravel. Listens to Uzbek bank notification bots (Click, Payme, Uzum) on Telegram via MTProto, parses incoming-transfer messages, and emits a Laravel `PaymentReceived` event your app can react to.

> Bank API yo'q? Provider integratsiyasi qimmat? Algorix Pay sizning hisobingizga kelayotgan bank botlari xabarlarini o'qiydi va to'lovni Laravel event sifatida yetkazadi.

---

## What it does

1. Logs into Telegram as a **userbot** (your account, via `MadelineProto` MTProto session).
2. Watches messages from configured bank bots (e.g. `@clickuz`, `@payme`, `@uzumbank_bot`).
3. Parses the message text with the matching `Driver` (regex + heuristics).
4. Dispatches `AlgorixPay\Events\PaymentReceived` with the parsed amount, currency, transaction ID, sender/receiver cards, and timestamp.

## Why MTProto (userbot) and not Bot API

Telegram's Bot API doesn't see messages between two regular users (or between a user and another bot). Bank notification "bots" send messages to the **account holder's chat** — a normal bot listener can't read them. A userbot session can.

## Requirements

- PHP **8.2+** (tested on 8.2 / 8.3 / 8.4)
- Laravel **11.x or 12.x**
- A Telegram account (for the userbot session)
- `api_id` / `api_hash` from <https://my.telegram.org>

## Install

```bash
composer require samdevxuz/algorix-pay
php artisan vendor:publish --tag=algorix-pay-config
```

## Configure

`.env`:

```env
ALGORIX_API_ID=1234567
ALGORIX_API_HASH=abcdef0123456789abcdef0123456789

ALGORIX_CLICK_ENABLED=true
ALGORIX_CLICK_SOURCE=clickuz

ALGORIX_PAYME_ENABLED=true
ALGORIX_PAYME_SOURCE=payme

ALGORIX_UZUM_ENABLED=true
ALGORIX_UZUM_SOURCE=uzumbank_bot

ALGORIX_DEDUP_TTL=600
ALGORIX_DEDUP_CACHE=redis
```

| Env | Default | Description |
|---|---|---|
| `ALGORIX_API_ID` | — | Telegram `api_id` (required) |
| `ALGORIX_API_HASH` | — | Telegram `api_hash` (required) |
| `ALGORIX_SESSION_PATH` | `storage/app/algorix-pay/userbot.madeline` | Persisted MTProto session path |
| `ALGORIX_<DRIVER>_ENABLED` | `false` (Click: `true`) | Enable a driver |
| `ALGORIX_<DRIVER>_SOURCE` | `clickuz` / `payme` / `uzumbank_bot` | Bot's Telegram username (lower-case, no `@`) |
| `ALGORIX_DEDUP_TTL` | `10` | Seconds to remember a `messageId` / `transactionId` for dedup. Bump to `600`+ in production. |
| `ALGORIX_DEDUP_CACHE` | default cache store | Cache store name (e.g. `redis`) used for dedup |
| `ALGORIX_LOG_CHANNEL` | `stack` | Log channel for parser/listener events |

## First-run login

The first time you run `php artisan pay:listen`, MadelineProto will prompt for phone number + login code in the terminal. The session is then persisted at `storage/app/algorix-pay/userbot.madeline` and reused on subsequent runs.

## Listen for payments

```php
use AlgorixPay\Events\PaymentReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (PaymentReceived $event): void {
    $payment = $event->payment;

    $payment->amountTiyin;     // int — 1 so'm = 100 tiyin
    $payment->currency;        // 'UZS' | 'RUB' | 'USD' | 'EUR'
    $payment->transactionId;   // ?string — bot-provided receipt/transaction id
    $payment->senderMasked;    // ?string — '8600****1234' (when present)
    $payment->receiverMasked;  // ?string — '9860****5678' (your card, when present)
    $payment->receivedAt;      // ?string — ISO 8601 UTC, from Telegram message.date
    $payment->rawText;         // string  — original bot message

    $event->bankMessageId;     // 'clickuz:12345' — UNIQUE per bot message
    $event->bankSource;        // 'clickuz'
});
```

## Run the listener

```bash
php artisan pay:listen
```

In production: run as a supervisor / systemd service — the loop blocks.

Example **supervisord** snippet:

```ini
[program:algorix-pay]
command=php /var/www/app/artisan pay:listen
autorestart=true
user=www-data
stderr_logfile=/var/log/algorix-pay.err.log
stdout_logfile=/var/log/algorix-pay.out.log
```

## Idempotency & dedup

Every parsed payment carries:

- `bank_message_id` of `"<source>:<telegram_message_id>"` — unique per bot message, safe as a `UNIQUE` column on your payments table.
- An additional **transaction-id-based dedup layer** (per source) catches bot reposts that arrive with a new `messageId` but the same `transactionId`.

Both layers use the configured cache store and respect `ALGORIX_DEDUP_TTL`.

## Money

All amounts are in **tiyin** — `1 so'm = 100 tiyin`. Format only at display time:

```php
number_format($payment->amountTiyin / 100, 0, '.', ' ').' so\'m';
```

## Architecture

```
src/
├── Console/
│   └── ListenPaymentsCommand.php   # php artisan pay:listen
├── Contracts/
│   └── PaymentDriver.php
├── Drivers/
│   ├── AbstractRegexDriver.php     # normalize + amount + currency + cards
│   ├── ClickDriver.php
│   ├── PaymeDriver.php
│   └── UzumDriver.php
├── Events/
│   └── PaymentReceived.php
├── Services/
│   └── MadelineService.php         # MTProto event loop + dedup + dispatch
├── Support/
│   └── ParsedPayment.php
└── AlgorixPayServiceProvider.php
```

## Writing your own driver

Extend `AbstractRegexDriver` (recommended) and override the three pattern hooks:

```php
namespace App\Algorix;

use AlgorixPay\Drivers\AbstractRegexDriver;

final class AnorDriver extends AbstractRegexDriver
{
    public function __construct(string $source = 'anorbank_bot')
    {
        parent::__construct($source);
    }

    protected function incomingMarkers(): array
    {
        return ['hisobingizga', 'tushdi', 'зачислен'];
    }

    protected function amountPatterns(): array
    {
        return [
            '/([0-9][0-9 .,]*)\s*(?:so\'?m|sum|сум|UZS)\b/iu',
        ];
    }

    protected function transactionPatterns(): array
    {
        return [
            '/(?:tranzaksiya|транзакция)\s*[:#№]\s*([A-Za-z0-9][A-Za-z0-9\-]{4,})/iu',
        ];
    }
}
```

Then register it in `config/algorix-pay.php`:

```php
'drivers' => [
    'anor' => [
        'enabled' => true,
        'source' => 'anorbank_bot',
        'class' => \App\Algorix\AnorDriver::class,
    ],
],
```

For a custom parser that doesn't fit the regex template, implement `AlgorixPay\Contracts\PaymentDriver` directly.

## Tests

```bash
composer install
./vendor/bin/phpunit
```

The suite covers:

- **Unit** — per-driver regex behavior across Uzbek/Russian, NBSP, comma/dot decimals, emoji, multiline.
- **Feature** — service provider wiring (Orchestra Testbench), `MadelineService` event dispatch, dedup, and edge cases (null peer, non-message updates, empty text).
- **Real-world** — emoji-rich multiline bot fixtures with arrow-notation card pairs.

## Security

This package handles a **userbot session** — treat the session file (`storage/app/algorix-pay/userbot.madeline`) as a credential. Don't commit it; restrict file permissions; rotate the Telegram session if leaked.

`ParsedPayment->rawText` contains the original bank message — log responsibly.

## License

MIT.
