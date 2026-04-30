# Algorix Pay

[![tests](https://github.com/SamDevXuz/algorix-pay/actions/workflows/tests.yml/badge.svg)](https://github.com/SamDevXuz/algorix-pay/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/samdevxuz/algorix-pay.svg)](https://packagist.org/packages/samdevxuz/algorix-pay)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Userbot-based P2P payment receiver for Laravel. Listens to Uzbek bank notification bots (Click, Payme, Uzum) on Telegram via MTProto, parses incoming-transfer messages, and emits a Laravel `PaymentReceived` event your app can react to.

> Bank API yo'q? Provider integratsiyasi qimmat? Algorix Pay sizning hisobingizga kelayotgan bank botlari xabarlarini o'qiydi va to'lovni Laravel event sifatida yetkazadi.

---

## English

### What it does

1. Logs into Telegram as a **userbot** (your account, via `MadelineProto` MTProto session).
2. Watches messages from configured bank bots (e.g. `@clickuz`, `@payme`, `@uzumbank_bot`).
3. Parses the message text with the matching `Driver` (regex + heuristics).
4. Dispatches `AlgorixPay\Events\PaymentReceived` with the parsed amount, transaction ID, etc.

### Why MTProto (userbot) and not Bot API

Telegram's Bot API doesn't see messages between two regular users (or between a user and another bot). Bank notification "bots" send messages to the **account holder's chat** — a normal bot listener can't read them. A userbot session can.

### Install

```bash
composer require samdevxuz/algorix-pay
php artisan vendor:publish --tag=algorix-pay-config
```

### Configure

`.env`:

```env
ALGORIX_API_ID=1234567
ALGORIX_API_HASH=abcdef0123456789abcdef0123456789

ALGORIX_CLICK_ENABLED=true
ALGORIX_CLICK_SOURCE=clickuz

ALGORIX_PAYME_ENABLED=false
ALGORIX_UZUM_ENABLED=false

ALGORIX_DEDUP_TTL=10
```

Get `api_id` / `api_hash` from <https://my.telegram.org>.

### First-run login

The first time you run `php artisan pay:listen`, MadelineProto will prompt for phone number + login code in the terminal. The session is then persisted at `storage/app/algorix-pay/userbot.madeline` and reused on subsequent runs.

### Listen for payments

```php
use AlgorixPay\Events\PaymentReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (PaymentReceived $event): void {
    $payment = $event->payment;

    // $payment->amountTiyin   // int, 1 sum = 100 tiyin
    // $payment->transactionId // ?string
    // $event->bankMessageId   // 'clickuz:12345' — UNIQUE, idempotency-safe
    // $event->bankSource      // 'clickuz'
});
```

### Run the listener

```bash
php artisan pay:listen
```

In production: run as a supervisor / systemd service (the loop blocks).

### Architecture

```
src/
├── Console/
│   └── ListenPaymentsCommand.php   # php artisan pay:listen
├── Drivers/
│   ├── ClickDriver.php             # Click messages → ParsedPayment
│   ├── PaymeDriver.php             # (stub — contributions welcome)
│   └── UzumDriver.php              # (stub — contributions welcome)
├── Events/
│   └── PaymentReceived.php
├── Services/
│   └── MadelineService.php         # MTProto event loop
├── Contracts/
│   └── PaymentDriver.php
├── Support/
│   └── ParsedPayment.php
└── AlgorixPayServiceProvider.php
```

### Writing your own driver

Implement `AlgorixPay\Contracts\PaymentDriver` and register it in `config/algorix-pay.php` under `drivers.<key>` with `enabled: true`, your `source` (the bot's Telegram username, lower-case, no `@`), and `class`.

### Money

All amounts are in **tiyin** — `1 so'm = 100 tiyin`. Format only at display time:

```php
number_format($payment->amountTiyin / 100, 0, '.', ' ').' so\'m';
```

### Idempotency

Every parsed payment carries a `bank_message_id` of `"<source>:<telegram_message_id>"`. This is unique per bank message and safe to use as a UNIQUE column on your payments table.

### License

MIT.

---

## O'zbekcha

### Bu nima

Algorix Pay — Laravel uchun ochiq kodli to'lov qabul qiluvchi paket. Sizning Telegram akkauntingizga kelayotgan bank xabarlarini (`@clickuz`, `@payme`, `@uzumbank_bot`) MTProto orqali o'qiydi, summa va tranzaksiya ID'sini ajratib, Laravel `PaymentReceived` event'i sifatida ilovangizga uzatadi.

### Nega kerak

- Click / Payme / Payze provayder shartnomasiz P2P karta-karta to'lovlarini avtomatik tasdiqlash uchun.
- Ortiqcha provayder komissiyasiz, oddiy bank xabari yetarli.
- Botingiz foydalanuvchidan **noyob summa** (masalan, 15 432 so'm) so'raydi — bank xabari kelganda summa orqali to'lovni topib, premium beradi.

### O'rnatish

```bash
composer require samdevxuz/algorix-pay
php artisan vendor:publish --tag=algorix-pay-config
```

### `.env` sozlash

```env
ALGORIX_API_ID=1234567
ALGORIX_API_HASH=abcdef0123456789abcdef0123456789
ALGORIX_CLICK_ENABLED=true
ALGORIX_CLICK_SOURCE=clickuz
```

`api_id` va `api_hash`'ni <https://my.telegram.org> dan olasiz.

### Ishga tushirish

```bash
php artisan pay:listen
```

Birinchi ishga tushirishda terminalda telefon raqam va Telegram kodi so'raladi. Session `storage/app/algorix-pay/userbot.madeline` ga saqlanadi.

### To'lov qabul qilish

```php
use AlgorixPay\Events\PaymentReceived;

Event::listen(function (PaymentReceived $event) {
    // Sizning to'lovingiz topildi:
    $event->payment->amountTiyin;   // tiyin (1 so'm = 100 tiyin)
    $event->payment->transactionId; // bankning chek raqami
    $event->bankMessageId;          // 'clickuz:54321' — unique
});
```

### Hissa qo'shish

`PaymeDriver` va `UzumDriver` hozircha stub. Pull-request kutamiz!

### Litsenziya

MIT — istagancha foydalaning, fork qiling, tijoratda ishlating.
