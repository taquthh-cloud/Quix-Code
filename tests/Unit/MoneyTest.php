<?php

use App\Enums\Currency;
use App\Support\Money;

it('memformat IDR sesuai locale', function () {
    $m = Money::of(1500000, Currency::IDR);

    expect($m->format('id'))->toBe('Rp 1.500.000')
        ->and($m->format('en'))->toBe('Rp 1,500,000');
});

it('memformat USD (sen) sesuai locale', function () {
    $m = Money::of(123450, Currency::USD);

    expect($m->format('en'))->toBe('$1,234.50')
        ->and($m->format('id'))->toBe('US$ 1.234,50');
});

it('menyimpan sen dengan benar untuk nilai kecil dan negatif', function () {
    expect(Money::of(5, Currency::USD)->format('en'))->toBe('$0.05')
        ->and(Money::of(-250000, Currency::IDR)->format('id'))->toBe('-Rp 250.000');
});

it('mem-parse satuan utama tanpa float', function () {
    expect(Money::fromMajor('1500000', Currency::IDR)->amount)->toBe(1500000)
        ->and(Money::fromMajor('1234.5', Currency::USD)->amount)->toBe(123450)
        ->and(Money::fromMajor('0.07', Currency::USD)->amount)->toBe(7);
});

it('menolak presisi desimal berlebih dan format salah', function () {
    Money::fromMajor('100.5', Currency::IDR);
})->throws(InvalidArgumentException::class);

it('menolak input bukan angka', function () {
    Money::fromMajor('abc', Currency::USD);
})->throws(InvalidArgumentException::class);

it('menjumlah dan mengurangi dalam mata uang sama', function () {
    $a = Money::of(100000, Currency::IDR);
    $b = Money::of(50000, Currency::IDR);

    expect($a->add($b)->amount)->toBe(150000)
        ->and($a->subtract($b)->amount)->toBe(50000);
});

it('menolak operasi lintas mata uang', function () {
    Money::of(1, Currency::IDR)->add(Money::of(1, Currency::USD));
})->throws(InvalidArgumentException::class);

it('menghitung jam x rate dan persen dengan pembulatan half up', function () {
    $rate = Money::of(150000, Currency::IDR);

    expect($rate->multiply('2.5')->amount)->toBe(375000)
        ->and(Money::of(1000001, Currency::IDR)->percent(20)->amount)->toBe(200000)
        ->and(Money::of(1, Currency::USD)->multiply('0.5')->amount)->toBe(1);
});

it('mengembalikan string satuan utama', function () {
    expect(Money::of(123405, Currency::USD)->toMajorString())->toBe('1234.05')
        ->and(Money::of(1500000, Currency::IDR)->toMajorString())->toBe('1500000');
});

it('helper money() tersedia', function () {
    expect(money(2000000, 'IDR')->format('id'))->toBe('Rp 2.000.000');
});
