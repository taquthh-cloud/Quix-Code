<?php

namespace App\Support;

/**
 * Skema termin pembayaran (Bagian 5C langkah 7): daftar persen dalam basis point yang totalnya 100%.
 * Selisih pembulatan selalu masuk termin terakhir, jadi jumlah semua termin = total persis.
 */
final class PaymentTerms
{
    public const MAX_TERMS = 6;

    /** Kunci pilihan builder (persen DP) -> basis point per termin. */
    private const PRESETS = [
        50 => [5000, 5000],
        30 => [3000, 4000, 3000],
    ];

    /** @return list<int> */
    public static function preset(int $dpPercent): array
    {
        return self::PRESETS[$dpPercent] ?? self::PRESETS[50];
    }

    /** @param  array<mixed>  $basisPoints */
    public static function isValid(array $basisPoints): bool
    {
        if (! array_is_list($basisPoints) || count($basisPoints) < 2 || count($basisPoints) > self::MAX_TERMS) {
            return false;
        }

        foreach ($basisPoints as $bp) {
            if (! is_int($bp) || $bp <= 0) {
                return false;
            }
        }

        return array_sum($basisPoints) === 10_000;
    }

    /**
     * "25, 25.5, 49.5" -> [2500, 2550, 4950]. Null bila formatnya salah (jumlah 100% dicek isValid).
     *
     * @return list<int>|null
     */
    public static function parseCustom(string $input): ?array
    {
        $parts = array_map('trim', explode(',', $input));
        $bps = [];

        foreach ($parts as $part) {
            if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $part) !== 1) {
                return null;
            }

            $bps[] = QuotationCalculator::percentToBp($part);
        }

        return $bps;
    }

    /**
     * Bagi total ke termin; semua termin kecuali yang terakhir dibulatkan half up.
     *
     * @param  list<int>  $basisPoints
     * @return list<Money>
     */
    public static function allocate(Money $total, array $basisPoints): array
    {
        $amounts = [];
        $allocated = Money::zero($total->currency);
        $last = count($basisPoints) - 1;

        foreach ($basisPoints as $i => $bp) {
            $amount = $i === $last
                ? $total->subtract($allocated)
                : $total->percent(QuotationCalculator::bpToPercent($bp));

            $amounts[] = $amount;
            $allocated = $allocated->add($amount);
        }

        return $amounts;
    }

    /**
     * Rincian tiap termin (T3-03): total, diskon, dan PPN dibagi mengikuti porsi termin, selisih
     * pembulatan masuk termin terakhir. Subtotal termin = total + diskon - PPN, jadi untuk setiap
     * termin berlaku subtotal - diskon + PPN = jumlah, dan jumlah tiap komponen = nilai quotation.
     *
     * @param  list<int>  $basisPoints
     * @return list<array{subtotal: Money, discount: Money, tax: Money, amount: Money}>
     */
    public static function allocateBreakdown(Money $total, Money $discount, Money $tax, array $basisPoints): array
    {
        $amounts = self::allocate($total, $basisPoints);
        $discounts = self::allocate($discount, $basisPoints);
        $taxes = self::allocate($tax, $basisPoints);

        $rows = [];

        foreach ($amounts as $i => $amount) {
            $rows[] = [
                'subtotal' => $amount->add($discounts[$i])->subtract($taxes[$i]),
                'discount' => $discounts[$i],
                'tax' => $taxes[$i],
                'amount' => $amount,
            ];
        }

        return $rows;
    }
}
