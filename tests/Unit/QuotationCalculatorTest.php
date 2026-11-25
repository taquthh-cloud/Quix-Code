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

it('menerapkan diskon sebelum PPN: (basis + margin - diskon) + PPN', function () {
    $calc = QuotationCalculator::calculate(Currency::IDR, [
        ['hours' => '10', 'rate' => 150_000],
        ['hours' => '12.5', 'rate' => 200_000],
    ], 500_000, '20', 400_000, 1100);

    expect($calc['subtotal'])->toBe(4_000_000)
        ->and($calc['margin'])->toBe(900_000)
        ->and($calc['discount'])->toBe(400_000)
        ->and($calc['taxable'])->toBe(5_000_000) // 4.000.000 + 500.000 + 900.000 - 400.000
        ->and($calc['tax'])->toBe(550_000) // 11% dari dasar pajak
        ->and($calc['total'])->toBe(5_550_000);
});

it('PPN dibulatkan sekali di akhir (half up) dalam sen USD', function () {
    $calc = QuotationCalculator::calculate(Currency::USD, [['hours' => '1.5', 'rate' => 1001]], 0, '12.5', 0, 1100);

    expect($calc['taxable'])->toBe(1690)
        ->and($calc['tax'])->toBe(186) // 11% x 1690 = 185,9
        ->and($calc['total'])->toBe(1876);
});

it('tarif PPN 0 berarti tanpa PPN', function () {
    $calc = QuotationCalculator::calculate(Currency::IDR, [['hours' => '100', 'rate' => 200_000]], 2_000_000, '20', 0, 0);

    expect($calc['tax'])->toBe(0)->and($calc['total'])->toBe(26_400_000);
});

it('menolak diskon melebihi basis + margin dan tarif di luar 0-100%', function () {
    $lines = [['hours' => '1', 'rate' => 100_000]];

    expect(fn () => QuotationCalculator::calculate(Currency::IDR, $lines, 0, '0', 100_001))
        ->toThrow(DomainException::class, 'quotations.errors.discount_too_large')
        ->and(fn () => QuotationCalculator::calculate(Currency::IDR, $lines, 0, '0', 0, 10_001))
        ->toThrow(DomainException::class);
});

it('mengubah persen <-> basis point tanpa float', function () {
    expect(QuotationCalculator::percentToBp('20'))->toBe(2000)
        ->and(QuotationCalculator::percentToBp('20.5'))->toBe(2050)
        ->and(QuotationCalculator::percentToBp('12.05'))->toBe(1205)
        ->and(QuotationCalculator::bpToPercent(1100))->toBe('11.00')
        ->and(QuotationCalculator::bpToPercent(5))->toBe('0.05');
});
