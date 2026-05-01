<?php

declare(strict_types=1);

namespace AlgorixPay\Drivers;

use AlgorixPay\Contracts\PaymentDriver;
use AlgorixPay\Support\ParsedPayment;

abstract class AbstractRegexDriver implements PaymentDriver
{
    public function __construct(
        protected readonly string $sourceUsername,
    ) {
    }

    public function source(): string
    {
        return $this->sourceUsername;
    }

    public function parse(string $text): ?ParsedPayment
    {
        $normalized = $this->normalize($text);

        if (! $this->looksLikeIncoming($normalized)) {
            return null;
        }

        $amount = $this->extractAmount($normalized);
        if ($amount === null) {
            return null;
        }

        [$amountTiyin, $currency] = $amount;

        return new ParsedPayment(
            source: $this->sourceUsername,
            amountTiyin: $amountTiyin,
            currency: $currency,
            transactionId: $this->extractTransactionId($normalized),
            senderMasked: $this->extractSender($normalized),
            receiverMasked: $this->extractReceiver($normalized),
            receivedAt: null,
            rawText: $text,
        );
    }

    /** @return list<string> */
    abstract protected function incomingMarkers(): array;

    /** @return list<string> */
    abstract protected function amountPatterns(): array;

    /** @return list<string> */
    abstract protected function transactionPatterns(): array;

    protected function normalize(string $text): string
    {
        $replacements = [
            "\u{00A0}" => ' ',
            "\u{202F}" => ' ',
            "\u{2009}" => ' ',
            "\u{2007}" => ' ',
            "\u{2192}" => ' -> ',
            "\u{27A1}" => ' -> ',
            "\u{2794}" => ' -> ',
        ];

        $clean = strtr($text, $replacements);

        $clean = (string) preg_replace('/[\p{So}\p{Cf}\p{Cs}]/u', ' ', $clean);

        $clean = (string) preg_replace('/[\r\n\t]+/u', ' ', $clean);

        return (string) preg_replace('/ {2,}/u', ' ', $clean);
    }

    protected function looksLikeIncoming(string $text): bool
    {
        if ($this->looksLikeOutgoing($text)) {
            return false;
        }

        $lower = mb_strtolower($text);

        foreach ($this->incomingMarkers() as $marker) {
            if (str_contains($lower, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeOutgoing(string $text): bool
    {
        $lower = mb_strtolower($text);

        $outgoing = [
            'yechildi', 'yechib olindi', "to'lov amalga oshirildi",
            'списан', 'списано', 'снят', 'оплачено',
            'withdrawn', 'debited', 'paid out',
        ];

        foreach ($outgoing as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:int,1:string}|null  [tiyin, currency]
     */
    protected function extractAmount(string $text): ?array
    {
        foreach ($this->amountPatterns() as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $tiyin = $this->amountToTiyin($m[1]);
                if ($tiyin === null || $tiyin <= 0) {
                    continue;
                }

                $currency = $this->detectCurrency($m[0]) ?? $this->detectCurrency($text) ?? 'UZS';

                return [$tiyin, $currency];
            }
        }

        return null;
    }

    protected function detectCurrency(string $fragment): ?string
    {
        $lower = mb_strtolower($fragment);

        if (preg_match('/(so\'?m|so\x{2018}m|sum|сум|узс|uzs)/iu', $lower) === 1) {
            return 'UZS';
        }

        if (preg_match('/(руб|rub|₽)/iu', $lower) === 1) {
            return 'RUB';
        }

        if (preg_match('/(usd|долл|\$)/iu', $lower) === 1) {
            return 'USD';
        }

        if (preg_match('/(eur|евро|€)/iu', $lower) === 1) {
            return 'EUR';
        }

        return null;
    }

    protected function extractTransactionId(string $text): ?string
    {
        foreach ($this->transactionPatterns() as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private const CARD_PATTERN = '\d{4}[\s*]*\*+[\s*]*\d{2,4}';

    protected function extractSender(string $text): ?string
    {
        $card = self::CARD_PATTERN;

        if (preg_match("/({$card})\s*(?:->|→|dan\b|→|с карты)/iu", $text, $m) === 1) {
            return $this->cleanCardMask($m[1]);
        }

        if (preg_match('/(?:from|jo\'natuvchi|отправител[ья]|с карты)[^0-9]{0,12}([0-9*]{8,})/iu', $text, $m) === 1) {
            return $this->cleanCardMask($m[1]);
        }

        return null;
    }

    protected function extractReceiver(string $text): ?string
    {
        $card = self::CARD_PATTERN;

        if (preg_match("/(?:->|→)\s*({$card})/iu", $text, $m) === 1) {
            return $this->cleanCardMask($m[1]);
        }

        if (preg_match("/({$card})\s*(?:ga\b|га\b|на карту)/iu", $text, $m) === 1) {
            return $this->cleanCardMask($m[1]);
        }

        if (preg_match("/(?:karta|карт[ае]|на карту|kartangiz)[^0-9]{0,8}({$card})/iu", $text, $m) === 1) {
            return $this->cleanCardMask($m[1]);
        }

        return null;
    }

    protected function cleanCardMask(string $raw): string
    {
        return (string) preg_replace('/\s+/', '', $raw);
    }

    protected function amountToTiyin(string $raw): ?int
    {
        $cleaned = (string) preg_replace('/[\s\']/u', '', $raw);

        if ($cleaned === '') {
            return null;
        }

        $hasComma = str_contains($cleaned, ',');
        $hasDot = str_contains($cleaned, '.');

        $sumPart = $cleaned;
        $tiyinPart = '';

        if ($hasComma && $hasDot) {
            $cleaned = str_replace(',', '', $cleaned);
            if (preg_match('/^(\d+)\.(\d{1,2})$/', $cleaned, $m) === 1) {
                $sumPart = $m[1];
                $tiyinPart = $m[2];
            } else {
                $sumPart = str_replace('.', '', $cleaned);
            }
        } elseif ($hasComma) {
            if (preg_match('/^\d{1,3}(,\d{3})+$/', $cleaned) === 1) {
                $sumPart = str_replace(',', '', $cleaned);
            } elseif (preg_match('/^(\d+),(\d{1,2})$/', $cleaned, $m) === 1) {
                $sumPart = $m[1];
                $tiyinPart = $m[2];
            } else {
                $sumPart = str_replace(',', '', $cleaned);
            }
        } elseif ($hasDot) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned) === 1) {
                $sumPart = str_replace('.', '', $cleaned);
            } elseif (preg_match('/^(\d+)\.(\d{1,2})$/', $cleaned, $m) === 1) {
                $sumPart = $m[1];
                $tiyinPart = $m[2];
            } else {
                $sumPart = str_replace('.', '', $cleaned);
            }
        }

        if (! ctype_digit($sumPart) || ($tiyinPart !== '' && ! ctype_digit($tiyinPart))) {
            return null;
        }

        $tiyinPart = str_pad($tiyinPart, 2, '0', STR_PAD_RIGHT);

        return (int) $sumPart * 100 + (int) substr($tiyinPart, 0, 2);
    }
}
