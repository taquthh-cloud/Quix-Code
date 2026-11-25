<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'title' => fake()->words(3, true),
            'qty' => 1,
            'unit_price' => 100_000,
            'amount' => 100_000,
            'sort_order' => 0,
        ];
    }
}
