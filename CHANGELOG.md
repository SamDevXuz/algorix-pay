# Changelog

All notable changes to `samdevxuz/algorix-pay` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/SamDevXuz/algorix-pay/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/SamDevXuz/algorix-pay/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/SamDevXuz/algorix-pay/releases/tag/v0.1.0
