<?php

namespace App\Support;

use App\Models\Quotation;

/**
 * Perbandingan dua versi quotation (T3-02): selisih angka utama dan perubahan per baris item.
 * Mode klien ($internal = false) menggabungkan operasional + margin, sama seperti kartu quotation klien.
 */
final class QuotationComparison
{
    /**
     * @return array{
     *     rows: list<array{label: string, type: 'money'|'text', old: int|string, new: int|string, delta: ?int, changed: bool}>,
     *     items: list<array{status: 'added'|'removed'|'changed'|'same', title: string, old: ?int, new: ?int}>,
     *     changed: bool
     * }
     */
    public static function between(Quotation $old, Quotation $new, bool $internal = false): array
    {
        $rows = [self::money('quotations.subtotal', $old->subtotal, $new->subtotal)];

        if ($internal) {
            $rows[] = self::money('quotations.builder.operational', $old->operational_cost, $new->operational_cost);
            $rows[] = self::money('quotations.builder.margin', $old->marginMoney()->amount, $new->marginMoney()->amount);
        } else {
            $rows[] = self::money(
                'quotations.overhead',
                $old->operational_cost + $old->marginMoney()->amount,
                $new->operational_cost + $new->marginMoney()->amount,
            );
        }

        $rows[] = self::money('quotations.builder.discount', (int) $old->discount_amount, (int) $new->discount_amount);
        $rows[] = self::money('quotations.compare.tax', (int) $old->tax_amount, (int) $new->tax_amount);
        $rows[] = self::money('quotations.total', $old->total, $new->total);
        $rows[] = self::money('quotations.compare.dp', $old->dpMoney()->amount, $new->dpMoney()->amount);
        $rows[] = self::text('quotations.compare.scheme', self::scheme($old), self::scheme($new));
        $rows[] = self::text(
            'quotations.valid_until',
            $old->valid_until?->format('Y-m-d') ?? '-',
            $new->valid_until?->format('Y-m-d') ?? '-',
        );

        $items = self::compareItems($old, $new);

        return [
            'rows' => $rows,
            'items' => $items,
            'changed' => collect($rows)->contains('changed', true) || collect($items)->contains(fn ($i) => $i['status'] !== 'same'),
        ];
    }

    /** @return list<array{status: 'added'|'removed'|'changed'|'same', title: string, old: ?int, new: ?int}> */
    private static function compareItems(Quotation $old, Quotation $new): array
    {
        $oldItems = self::indexItems($old);
        $newItems = self::indexItems($new);
        $result = [];

        foreach ($newItems as $key => $item) {
            if (! isset($oldItems[$key])) {
                $result[] = ['status' => 'added', 'title' => $item['title'], 'old' => null, 'new' => $item['amount']];

                continue;
            }

            $same = $oldItems[$key]['fingerprint'] === $item['fingerprint'];
            $result[] = [
                'status' => $same ? 'same' : 'changed',
                'title' => $item['title'],
                'old' => $oldItems[$key]['amount'],
                'new' => $item['amount'],
            ];
        }

        foreach ($oldItems as $key => $item) {
            if (! isset($newItems[$key])) {
                $result[] = ['status' => 'removed', 'title' => $item['title'], 'old' => $item['amount'], 'new' => null];
            }
        }

        return $result;
    }

    /**
     * Item dicocokkan lewat judul (tanpa beda huruf besar/kecil); judul kembar dicocokkan menurut urutan kemunculan.
     *
     * @return array<string, array{title: string, amount: int, fingerprint: string}>
     */
    private static function indexItems(Quotation $quotation): array
    {
        $items = $quotation->relationLoaded('items') ? $quotation->items : $quotation->items()->get();
        $seen = [];
        $indexed = [];

        foreach ($items->sortBy('sort_order') as $item) {
            $base = mb_strtolower(trim((string) $item->title));
            $seen[$base] = ($seen[$base] ?? 0) + 1;

            $indexed[$base.'#'.$seen[$base]] = [
                'title' => (string) $item->title,
                'amount' => (int) $item->amount,
                'fingerprint' => implode('|', [
                    trim((string) $item->description),
                    self::hours((string) $item->hours),
                    (int) $item->rate,
                    (int) $item->amount,
                ]),
            ];
        }

        return $indexed;
    }

    private static function hours(string $hours): string
    {
        return str_contains($hours, '.') ? (rtrim(rtrim($hours, '0'), '.') ?: '0') : $hours;
    }

    private static function scheme(Quotation $quotation): string
    {
        return implode('-', array_map(
            fn (int $bp) => rtrim(rtrim(QuotationCalculator::bpToPercent($bp), '0'), '.') ?: '0',
            $quotation->termBps(),
        ));
    }

    /** @return array{label: string, type: 'money', old: int, new: int, delta: int, changed: bool} */
    private static function money(string $label, int $old, int $new): array
    {
        return ['label' => $label, 'type' => 'money', 'old' => $old, 'new' => $new, 'delta' => $new - $old, 'changed' => $old !== $new];
    }

    /** @return array{label: string, type: 'text', old: string, new: string, delta: null, changed: bool} */
    private static function text(string $label, string $old, string $new): array
    {
        return ['label' => $label, 'type' => 'text', 'old' => $old, 'new' => $new, 'delta' => null, 'changed' => $old !== $new];
    }
}
