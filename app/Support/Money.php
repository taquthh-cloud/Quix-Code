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
    ) {}

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

    /** Kali faktor desimal tanpa aritmetika float, dibulatkan half up. */
    public function multiply(int|string $factor): self
    {
        [$numerator, $denominator] = self::decimalRatio($factor);

        return $this->multiplyRatio($numerator, $denominator);
    }

    /** Persentase dari nilai ini (mis. margin "20.5"), dibulatkan half up. */
    public function percent(int|string $percent): self
    {
        [$numerator, $denominator] = self::decimalRatio($percent);

        return $this->multiplyRatio($numerator, $denominator * 100);
    }

    /** @return array{int, int} */
    private static function decimalRatio(int|string $value): array
    {
        $value = (string) $value;

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Faktor desimal tidak valid: {$value}");
        }

        $fraction = rtrim($matches[3] ?? '', '0');
        if (strlen($fraction) > 6) {
            throw new InvalidArgumentException('Maksimal enam digit desimal didukung.');
        }

        $denominator = 10 ** strlen($fraction);
        $whole = (int) $matches[2];
        $fractionValue = $fraction === '' ? 0 : (int) str_pad($fraction, strlen($fraction), '0');

        if ($whole > intdiv(PHP_INT_MAX - $fractionValue, $denominator)) {
            throw new InvalidArgumentException('Faktor desimal terlalu besar.');
        }

        $numerator = $whole * $denominator + $fractionValue;
        if (($matches[1] ?? '') === '-') {
            $numerator *= -1;
        }

        return [$numerator, $denominator];
    }

    private function multiplyRatio(int $numerator, int $denominator): self
    {
        if ($denominator <= 0 || $this->amount === PHP_INT_MIN || $numerator === PHP_INT_MIN) {
            throw new InvalidArgumentException('Rasio uang di luar rentang yang didukung.');
        }

        $negative = ($this->amount < 0) !== ($numerator < 0);
        $amount = abs($this->amount);
        $factor = abs($numerator);

        if ($factor !== 0 && $amount > intdiv(PHP_INT_MAX, $factor)) {
            throw new InvalidArgumentException('Hasil perhitungan uang terlalu besar.');
        }

        $scaled = $amount * $factor;
        $rounded = intdiv($scaled, $denominator);
        $remainder = $scaled % $denominator;

        if ($remainder >= intdiv($denominator + 1, 2)) {
            $rounded++;
        }

        if ($rounded === PHP_INT_MIN && $negative) {
            throw new InvalidArgumentException('Hasil perhitungan uang terlalu besar.');
        }

        return new self($negative ? -$rounded : $rounded, $this->currency);
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
