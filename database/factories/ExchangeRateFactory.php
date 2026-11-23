<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'base_currency' => Currency::IDR,
            'target_currency' => Currency::USD,
            'rate' => 0.00006100,
            'fetched_at' => now(),
        ];
    }
}
