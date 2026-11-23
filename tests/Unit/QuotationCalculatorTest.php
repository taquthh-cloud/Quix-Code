<?php

use App\Enums\Currency;
use App\Support\QuotationCalculator;

it('menghitung (jam x rate) + operasional + margin untuk IDR', function () {
    $calc = QuotationCalculator::calculate(Currency::IDR, [
        ['hours' => '10', 'rate' => 150_000],
        ['hours' => '12.5', 'rate' => 200_000],
    ], 500_000, '20');

    expect($calc['amounts'])->toBe([1_500_000, 2_500_000])
        ->and($calc['subtotal'])->toBe(4_000_000)
        ->and($calc['operational'])->toBe(500_000)
        ->and($calc['margin'])->toBe(900_000) // 20% dari (4.000.000 + 500.000)
        ->and($calc['total'])->toBe(5_400_000);
});

it('menghitung dalam sen untuk USD dan membulatkan half up', function () {
    // 1,5 jam x $10,01 = 1501,5 sen -> 1502 sen
    $calc = QuotationCalculator::calculate(Currency::USD, [['hours' => '1.5', 'rate' => 1001]], 0, '12.5');

    expect($calc['amounts'])->toBe([1502])
        ->and($calc['subtotal'])->toBe(1502)
        ->and($calc['margin'])->toBe(188) // 12,5% x 1502 = 187,75
        ->and($calc['total'])->toBe(1690);
});

it('tanpa baris dan tanpa margin totalnya hanya biaya operasional', function () {
    $calc = QuotationCalculator::calculate(Currency::IDR, [], 250_000, '0');

    expect($calc['subtotal'])->toBe(0)
        ->and($calc['margin'])->toBe(0)
        ->and($calc['total'])->toBe(250_000);
});

it('cocok dengan nilai default factory quotation', function () {
    // QuotationFactory: subtotal 20 jt + operasional 2 jt, margin 20% -> 26,4 jt
    $calc = QuotationCalculator::calculate(Currency::IDR, [['hours' => '100', 'rate' => 200_000]], 2_000_000, '20');

    expect($calc['total'])->toBe(26_400_000);
});
