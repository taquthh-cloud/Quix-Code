<?php

namespace App\Support;

use App\Models\Quotation;

/**
 * Sidik jari isi quotation saat disetujui (T3-05). Hash SHA-256 atas JSON kanonik angka & baris item,
 * tidak termasuk status/waktu, sehingga perubahan harga atau lingkup sesudah persetujuan terdeteksi.
 */
final class QuotationSnapshot
{
    /** @return array<string, mixed> */
    public static function payload(Quotation $quotation): array
    {
        $items = $quotation->relationLoaded('items') ? $quotation->items : $quotation->items()->get();

        return [
            'project_id' => (string) $quotation->project_id,
            'version' => (int) $quotation->version,
            'currency' => $quotation->currency->value,
            'subtotal' => (int) $quotation->subtotal,
            'operational_cost' => (int) $quotation->operational_cost,
            'margin_bp' => (int) $quotation->margin_bp,
            'discount_amount' => (int) $quotation->discount_amount,
            'tax_bp' => (int) $quotation->tax_bp,
            'tax_amount' => (int) $quotation->tax_amount,
            'total' => (int) $quotation->total,
            'payment_terms' => $quotation->termBps(),
            'valid_until' => $quotation->valid_until?->format('Y-m-d'),
            'items' => $items->sortBy('sort_order')->values()->map(fn ($item) => [
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'hours' => (string) $item->hours,
                'rate' => (int) $item->rate,
                'amount' => (int) $item->amount,
                'sort_order' => (int) $item->sort_order,
            ])->all(),
        ];
    }

    public static function hash(Quotation $quotation): string
    {
        return hash('sha256', json_encode(self::payload($quotation), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
