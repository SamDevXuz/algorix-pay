<?php

declare(strict_types=1);

namespace AlgorixPay\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class PendingPayment
{
    public function __construct(
        public readonly int $amountTiyin,
        public readonly int $baseTiyin,
        public readonly string $currency,
        public readonly string $humanAmount,
        public readonly ?string $payableType,
        public readonly ?string $payableId,
        public readonly array $meta,
        public readonly string $expiresAt,
        public readonly string $reference,
    ) {
    }

    public static function format(int $amountTiyin, string $currency): string
    {
        $sum = intdiv($amountTiyin, 100);
        $tiyin = $amountTiyin % 100;

        $sumFormatted = number_format($sum, 0, '.', ' ');

        $suffix = match (strtoupper($currency)) {
            'UZS' => "so'm",
            default => strtoupper($currency),
        };

        if ($tiyin === 0) {
            return $sumFormatted.' '.$suffix;
        }

        return $sumFormatted.'.'.str_pad((string) $tiyin, 2, '0', STR_PAD_LEFT).' '.$suffix;
    }

    public function resolvePayable(): ?Model
    {
        if ($this->payableType === null || $this->payableId === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->payableType) ?? $this->payableType;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $class */
        return $class::query()->find($this->payableId);
    }

    public function toArray(): array
    {
        return [
            'amount_tiyin' => $this->amountTiyin,
            'base_tiyin' => $this->baseTiyin,
            'currency' => $this->currency,
            'human_amount' => $this->humanAmount,
            'payable_type' => $this->payableType,
            'payable_id' => $this->payableId,
            'meta' => $this->meta,
            'expires_at' => $this->expiresAt,
            'reference' => $this->reference,
        ];
    }
}
