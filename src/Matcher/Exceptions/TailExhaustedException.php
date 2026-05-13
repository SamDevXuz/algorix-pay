<?php

declare(strict_types=1);

namespace AlgorixPay\Matcher\Exceptions;

final class TailExhaustedException extends \RuntimeException
{
    public static function forBase(int $baseTiyin, int $attempts): self
    {
        return new self(sprintf(
            'Could not find a free amount tail for base %d after %d attempts.',
            $baseTiyin,
            $attempts,
        ));
    }
}
