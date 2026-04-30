# Contributing to Algorix Pay

Thanks for your interest! This package powers real-money flows, so we keep the bar high.

## How you can help

Highest-impact contributions right now:

1. **New drivers** — additional Uzbek bank notification bots (Anor, Asaka, TBC, Hamkorbank, Davr, etc.).
2. **Real-world message samples** — open an issue with anonymized Click / Payme / Uzum messages so we can harden the regexes.
3. **Translations** — Russian / Karakalpak READMEs.

## Development setup

```bash
git clone https://github.com/SamDevXuz/algorix-pay.git
cd algorix-pay
composer install
vendor/bin/phpunit --testdox
```

PHP 8.2+ required. No external services needed for the test suite — all parser tests are pure PHP.

## Adding a new driver

1. Create `src/Drivers/YourBankDriver.php` extending `AbstractRegexDriver`.
2. Implement `incomingMarkers()`, `amountPatterns()`, `transactionPatterns()`.
3. Register it in `config/algorix-pay.php` under `drivers.<key>`.
4. Add a test class in `tests/Unit/YourBankDriverTest.php` with at least:
   - one passing incoming message,
   - one outgoing message that must return `null`,
   - one unrelated message that must return `null`.

## Code style

- `declare(strict_types=1);` on every PHP file under `src/`.
- `final class` unless inheritance is required.
- Constructor property promotion + `private readonly` where possible.
- Money is stored in **tiyin** (`1 so'm = 100 tiyin`). Never store decimals.
- No emojis in code. README and Telegram-facing strings can use them.

## Pull request checklist

- [ ] Tests pass: `vendor/bin/phpunit`
- [ ] New behavior is covered by a test
- [ ] No real phone numbers, card numbers, or secrets in fixtures
- [ ] README updated if public API changed

## Reporting security issues

Email **samdevxuz@proton.me** instead of opening a public issue. We aim to respond within 72 hours.
