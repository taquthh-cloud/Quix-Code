<?php

namespace App\Support;

use App\Enums\Currency;

/**
 * Hitungan quotation (Bagian 5): (jam × rate) + operasional + margin.
 * Margin dihitung dari (subtotal + operasional). Semua dalam satuan terkecil, pembulatan half up.
 */
final class QuotationCalculator
{
    /**
     * @param  list<array{hours: string, rate: int}>  $lines  rate = per jam, satuan terkecil
     * @return array{amounts: list<int>, subtotal: int, operational: int, margin: int, total: int}
     */
    public static function calculate(Currency $currency, array $lines, int $operational, string $marginPercent): array
    {
        $amounts = [];
        $subtotal = Money::zero($currency);

        foreach ($lines as $line) {
            $amount = Money::of($line['rate'], $currency)->multiply($line['hours']);
            $amounts[] = $amount->amount;
            $subtotal = $subtotal->add($amount);
        }

        $base = $subtotal->add(Money::of($operational, $currency));
        $margin = $base->percent($marginPercent);

        return [
            'amounts' => $amounts,
            'subtotal' => $subtotal->amount,
            'operational' => $operational,
            'margin' => $margin->amount,
            'total' => $base->add($margin)->amount,
        ];
    }
}
