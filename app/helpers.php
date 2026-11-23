<?php

use App\Enums\Currency;
use App\Support\Money;

if (! function_exists('money')) {
    /** money(1500000, 'IDR')->format() */
    function money(int $minorUnits, Currency|string $currency = Currency::IDR): Money
    {
        return Money::of($minorUnits, $currency);
    }
}
