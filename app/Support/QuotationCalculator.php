<?php

namespace App\Support;

use App\Enums\Currency;
use DomainException;

/**
 * Hitungan quotation (Bagian 5C):
 *   basis = subtotal + operasional; margin = basis x margin;
 *   dasar pajak = basis + margin - diskon; PPN = dasar pajak x tax_bp / 10.000; total = dasar pajak + PPN.
 * Semua dalam satuan terkecil, pembulatan half up (per baris, margin, dan PPN sekali di akhir).
 */
final class QuotationCalculator
{
    /**
     * @param  list<array{hours: string, rate: int}>  $lines  rate = per jam, satuan terkecil
     * @param  int  $discount  diskon nominal (satuan terkecil), dikurangkan dari basis + margin
     * @param  int  $taxBp  tarif PPN dalam basis point (1100 = 11,00%); 0 = tanpa PPN
     * @return array{amounts: list<int>, subtotal: int, operational: int, margin: int, discount: int, taxable: int, tax_bp: int, tax: int, total: int}
     */
    public static function calculate(
        Currency $currency,
        array $lines,
        int $operational,
        string $marginPercent,
        int $discount = 0,
        int $taxBp = 0,
    ): array {
        if ($discount < 0 || $taxBp < 0 || $taxBp > 10_000) {
            throw new DomainException('quotations.errors.invalid_amount');
        }

        $amounts = [];
        $subtotal = Money::zero($currency);

        foreach ($lines as $line) {
            $amount = Money::of($line['rate'], $currency)->multiply($line['hours']);
            $amounts[] = $amount->amount;
            $subtotal = $subtotal->add($amount);
        }

        $base = $subtotal->add(Money::of($operational, $currency));
        $margin = $base->percent($marginPercent);
        $taxable = $base->add($margin)->subtract(Money::of($discount, $currency));

        if ($taxable->isNegative()) {
            throw new DomainException('quotations.errors.discount_too_large');
        }

        $tax = $taxable->percent(self::bpToPercent($taxBp));

        return [
            'amounts' => $amounts,
            'subtotal' => $subtotal->amount,
            'operational' => $operational,
            'margin' => $margin->amount,
            'discount' => $discount,
            'taxable' => $taxable->amount,
            'tax_bp' => $taxBp,
            'tax' => $tax->amount,
            'total' => $taxable->add($tax)->amount,
        ];
    }

    /** "20.5" -> 2050. Persentase 0-100 dengan maksimal 2 desimal. */
    public static function percentToBp(string $percent): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($percent), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /** 1100 -> "11.00". */
    public static function bpToPercent(int $bp): string
    {
        return intdiv($bp, 100).'.'.str_pad((string) ($bp % 100), 2, '0', STR_PAD_LEFT);
    }
}
