# Changelog

All notable changes to `samdevxuz/algorix-pay` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] — 2026-05-13

### Added
- **Order matching** — turn the package from a parser into a P2P payment gateway.
  - `AlgorixPay::expect(50_000)->forOrder($order)->expiresInMinutes(15)->create()` fluent API returns a `PendingPayment` with a unique amount tail (e.g. `5_000_037` tiyin).
  - When a `PaymentReceived` event arrives with a matching `amountTiyin`, the new `MatchPendingPayment` listener atomically pulls the expectation from cache and dispatches `PaymentMatched($pending, $payment, $bankMessageId, $bankSource)`.
- `\AlgorixPay\Facades\AlgorixPay` facade backed by `AlgorixPayManager` (hands out a fresh `PaymentExpectation` per call — no state bleed between callers).
- `\AlgorixPay\Contracts\TailGenerator` contract with two implementations:
  - `TiyinTailGenerator` (default, 99 slots, `50 000.37 so'm` style).
  - `SumTailGenerator` (999 slots, `50 137 so'm` style).
  Pluggable via `matcher.tail_generators` config map.
- `\AlgorixPay\Support\PendingPayment` value object with scalar-only fields, `resolvePayable()` Eloquent lookup, and `toArray()` symmetric with `ParsedPayment`.
- `\AlgorixPay\Events\PaymentExpected` event for observability — dispatched on `create()`.
- `matcher` config section with `ALGORIX_MATCHER_*` env vars: `ENABLED`, `CACHE`, `TTL`, `PREFIX`, `CURRENCY`, `TAIL_MODE`, `MAX_ATTEMPTS`, `CURRENCY_MISMATCH`.
- `TailExhaustedException` thrown when all tail slots for a base amount are taken.

### Changed
- `AlgorixPayServiceProvider` extracts a shared `resolveCacheStore()` helper used by both the dedup and matcher cache lookups.

### Notes
- Cache-only storage; no DB migration. The matcher's pending records live behind the configured cache store (Redis recommended for production).
- Currency mismatch policy is configurable: `drop` (silent skip), `log` (default — warn + skip), `match_anyway` (proceed with warning).

## [0.2.0] — 2026-05-01

### Added
- Currency detection from token context (UZS / RUB / USD / EUR) — replaces hardcoded `'UZS'`.
- `ParsedPayment::receivedAt` now wired from Telegram `message.date` as ISO 8601 UTC.
- Transaction-id-based dedup layer on top of `messageId` dedup, to catch bot reposts arriving with new message ids.
- Sender / receiver extraction via directional cues (`dan` / `ga` / `from` / `to` / `→`) and arrow-notation card pairs (`8600****1234 → 9860****5678`).
- `ParsedPayment::withReceivedAt()` immutable helper.
- `MadelineService::processUpdates(iterable)` — extracted from the blocking `getUpdates` loop so handlers can be exercised in tests.
- Orchestra Testbench-based **Feature** test suite covering service-provider wiring, event dispatch, dedup, peer/edge cases, and real-world emoji-rich multiline bot fixtures.

### Changed
- `AbstractRegexDriver::normalize()` now strips emoji / format / surrogate code points and collapses `\n\r\t` to a single space; unicode arrows (`→ ➡ ➔`) are mapped to ` -> `.
- Transaction-id patterns require an explicit `:` / `#` / `№` delimiter (avoids capturing the next word as the id).
- Amount-token regexes now use `\b` word boundaries (fixes the `Summa: 75 000 so'm` next to `Karta: 8600****1111` false-positive that returned `1111` because `sum` matched the start of `Summa`).
- `MadelineService` is no longer `final`; `extractMessage` / `extractPeerUsername` / `handleUpdate` are `protected` to allow stubbing peer resolution in tests.

### Fixed
- `Summa→sum` 1111-tiyin false-positive on multiline bot messages where a card number preceded the actual amount line.

## [0.1.0] — 2026-04-30

### Added
- Initial release.
- MTProto userbot listener via MadelineProto.
- Drivers for Click, Payme, Uzum.
- `PaymentReceived` event with `ParsedPayment` payload.
- `messageId`-based dedup with configurable TTL and cache store.
- `pay:listen` artisan command.

[Unreleased]: https://github.com/SamDevXuz/algorix-pay/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/SamDevXuz/algorix-pay/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/SamDevXuz/algorix-pay/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/SamDevXuz/algorix-pay/releases/tag/v0.1.0
