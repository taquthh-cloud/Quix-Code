<?php

namespace App\Enums;

enum Currency: string
{
    case IDR = 'IDR';
    case USD = 'USD';

    /** Jumlah digit desimal mata uang (IDR 0, USD 2). */
    public function decimals(): int
    {
        return match ($this) {
            self::IDR => 0,
            self::USD => 2,
        };
    }

    /** Pengali satuan terkecil: IDR 1 (rupiah), USD 100 (sen). */
    public function minorFactor(): int
    {
        return 10 ** $this->decimals();
    }

    public function symbol(string $locale = 'id'): string
    {
        return match ($this) {
            self::IDR => 'Rp',
            self::USD => $locale === 'id' ? 'US$' : '$',
        };
    }

    public function label(): string
    {
        return __("enums.currency.{$this->value}");
    }
}
