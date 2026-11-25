<?php

use App\Enums\Currency;
use App\Support\Money;
use App\Support\PaymentTerms;

it('selisih pembulatan masuk termin terakhir sehingga jumlah termin sama dengan total', function () {
    $amounts = PaymentTerms::allocate(Money::of(1_000_001, Currency::IDR), [3000, 4000, 3000]);

    expect(array_map(fn (Money $m) => $m->amount, $amounts))->toBe([300_000, 400_000, 300_001])
        ->and(array_sum(array_map(fn (Money $m) => $m->amount, $amounts)))->toBe(1_000_001);
});

it('membagi sen USD dengan selisih di termin terakhir', function () {
    $amounts = PaymentTerms::allocate(Money::of(10_001, Currency::USD), [3333, 3333, 3334]);

    expect(array_map(fn (Money $m) => $m->amount, $amounts))->toBe([3333, 3333, 3335]);
});

it('hanya menerima 2-6 termin positif yang totalnya tepat 100%', function () {
    expect(PaymentTerms::isValid([5000, 5000]))->toBeTrue()
        ->and(PaymentTerms::isValid([3000, 4000, 3000]))->toBeTrue()
        ->and(PaymentTerms::isValid([10000]))->toBeFalse()
        ->and(PaymentTerms::isValid([6000, 6000]))->toBeFalse()
        ->and(PaymentTerms::isValid([5000, 4999]))->toBeFalse()
        ->and(PaymentTerms::isValid([10000, 0]))->toBeFalse()
        ->and(PaymentTerms::isValid([1000, 1000, 1000, 1000, 1000, 1000, 4000]))->toBeFalse();
});

it('mengurai isian kustom dalam persen dan menolak format salah', function () {
    expect(PaymentTerms::parseCustom('25, 25.5 ,49.5'))->toBe([2500, 2550, 4950])
        ->and(PaymentTerms::parseCustom('25, abc'))->toBeNull()
        ->and(PaymentTerms::parseCustom('25,,75'))->toBeNull()
        ->and(PaymentTerms::parseCustom('12.345, 87.655'))->toBeNull();
});

it('preset 50-50 dan 30-40-30', function () {
    expect(PaymentTerms::preset(50))->toBe([5000, 5000])
        ->and(PaymentTerms::preset(30))->toBe([3000, 4000, 3000]);
});

it('rincian termin membagi diskon dan PPN mengikuti porsi, selisih pembulatan di termin terakhir', function () {
    $rows = PaymentTerms::allocateBreakdown(
        Money::of(100, Currency::IDR),
        Money::of(10, Currency::IDR),
        Money::of(7, Currency::IDR),
        [3333, 3333, 3334],
    );
    $sum = fn (string $key) => array_sum(array_map(fn (array $row) => $row[$key]->amount, $rows));

    expect($sum('amount'))->toBe(100)
        ->and($sum('discount'))->toBe(10)
        ->and($sum('tax'))->toBe(7)
        ->and($sum('subtotal'))->toBe(103); // total + diskon - PPN

    foreach ($rows as $row) {
        expect($row['subtotal']->amount - $row['discount']->amount + $row['tax']->amount)->toBe($row['amount']->amount);
    }
});
