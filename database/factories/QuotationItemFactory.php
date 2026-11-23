<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationItem> */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        $hours = fake()->numberBetween(4, 40);
        $rate = 150_000;

        return [
            'quotation_id' => Quotation::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'hours' => $hours,
            'rate' => $rate,
            'amount' => $hours * $rate,
            'sort_order' => 0,
        ];
    }
}
