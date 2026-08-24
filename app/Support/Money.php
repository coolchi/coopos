<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class Money implements JsonSerializable, Stringable
{
    public function __construct(
        private readonly int $kobo,
        private readonly string $currency = 'NGN',
    ) {
        if ($this->kobo < 0) {
            throw new InvalidArgumentException('Money amounts cannot be negative.');
        }
    }

    public static function of(int $kobo, string $currency = 'NGN'): self
    {
        return new self($kobo, $currency);
    }

    public static function zero(string $currency = 'NGN'): self
    {
        return new self(0, $currency);
    }

    public static function fromNaira(float|int|string $naira, string $currency = 'NGN'): self
    {
        $normalized = is_string($naira)
            ? str_replace([',', '₦', ' '], '', $naira)
            : (string) $naira;

        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new InvalidArgumentException('Invalid naira amount.');
        }

        return new self((int) round(((float) $normalized) * 100), $currency);
    }

    public function kobo(): int
    {
        return $this->kobo;
    }

    public function naira(): float
    {
        return $this->kobo / 100;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->kobo + $other->kobo, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->kobo > $this->kobo) {
            throw new InvalidArgumentException('Insufficient amount.');
        }

        return new self($this->kobo - $other->kobo, $this->currency);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Factor cannot be negative.');
        }

        return new self($this->kobo * $factor, $this->currency);
    }

    public function percentage(int $basisPoints): self
    {
        return new self((int) round($this->kobo * $basisPoints / 10000), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->kobo === $other->kobo;
    }

    public static function format(int $kobo, string $currency = 'NGN'): string
    {
        $decimals = $kobo % 100 === 0 ? 0 : 2;
        $naira = number_format($kobo / 100, $decimals);

        return $currency === 'NGN' ? "₦{$naira}" : "{$currency} {$naira}";
    }

    public function jsonSerialize(): array
    {
        return [
            'kobo' => $this->kobo,
            'currency' => $this->currency,
            'formatted' => (string) $this,
        ];
    }

    public function __toString(): string
    {
        return self::format($this->kobo, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
