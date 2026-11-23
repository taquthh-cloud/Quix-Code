<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

/**
 * Nilai uang immutable. $amount selalu dalam satuan terkecil (rupiah / sen).
 * Semua format uang di aplikasi harus lewat kelas ini.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {
    }

    public static function of(int $minorUnits, Currency|string $currency): self
    {
        return new self($minorUnits, $currency instanceof Currency ? $currency : Currency::from($currency));
    }

    /**
     * Dari input satuan utama ("1500000", "1500.50"), tanpa float.
     * Titik dipakai sebagai pemisah desimal; hilangkan pemisah ribuan sebelum memanggil.
     */
    public static function fromMajor(string|int $value, Currency|string $currency): self
    {
        $currency = $currency instanceof Currency ? $currency : Currency::from($currency);
        $value = trim((string) $value);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $m)) {
            throw new InvalidArgumentException("Format angka tidak valid: {$value}");
        }

        [, $sign, $int, $frac] = $m + [3 => ''];
        $frac ??= '';
        $decimals = $currency->decimals();

        if (strlen($frac) > $decimals && rtrim(substr($frac, $decimals), '0') !== '') {
            throw new InvalidArgumentException(
                "{$currency->value} hanya mendukung {$decimals} digit desimal: {$value}"
            );
        }

        $frac = str_pad(substr($frac, 0, $decimals), $decimals, '0');
        $minor = (int) ($int.$frac);

        return new self($sign === '-' ? -$minor : $minor, $currency);
    }

    public static function zero(Currency|string $currency): self
    {
        return self::of(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    /** Kali faktor (mis. jam × rate), dibulatkan ke satuan terkecil (half up). */
    public function multiply(int|float|string $factor): self
    {
        return new self((int) round($this->amount * (float) $factor, 0, PHP_ROUND_HALF_UP), $this->currency);
    }

    /** Persentase dari nilai ini (mis. margin 20.5%), dibulatkan half up. */
    public function percent(int|float|string $percent): self
    {
        return $this->multiply((float) $percent / 100);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /** Satuan utama sebagai string tanpa pemisah ribuan: "1500000" / "1234.50". */
    public function toMajorString(): string
    {
        $decimals = $this->currency->decimals();
        $abs = abs($this->amount);
        $factor = $this->currency->minorFactor();
        $sign = $this->amount < 0 ? '-' : '';

        if ($decimals === 0) {
            return $sign.$abs;
        }

        return $sign.intdiv($abs, $factor).'.'.str_pad((string) ($abs % $factor), $decimals, '0', STR_PAD_LEFT);
    }

    /**
     * id: "Rp 1.500.000", "US$ 1.234,50"
     * en: "Rp 1,500,000", "$1,234.50"
     */
    public function format(?string $locale = null): string
    {
        $locale = ($locale ?? app()->getLocale()) === 'en' ? 'en' : 'id';
        [$thousands, $decimalPoint] = $locale === 'id' ? ['.', ','] : [',', '.'];

        $decimals = $this->currency->decimals();
        $abs = abs($this->amount);
        $factor = $this->currency->minorFactor();

        $number = number_format(intdiv($abs, $factor), 0, '', $thousands);
        if ($decimals > 0) {
            $number .= $decimalPoint.str_pad((string) ($abs % $factor), $decimals, '0', STR_PAD_LEFT);
        }

        $symbol = $this->currency->symbol($locale);
        $space = ($this->currency === Currency::IDR || $locale === 'id') ? ' ' : '';

        return ($this->amount < 0 ? '-' : '').$symbol.$space.$number;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Mata uang berbeda: {$this->currency->value} vs {$other->currency->value}"
            );
        }
    }
}
