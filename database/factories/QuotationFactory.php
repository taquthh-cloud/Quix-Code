<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\QuotationStatus;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version' => 1,
            'currency' => Currency::IDR,
            'subtotal' => 20_000_000,
            'operational_cost' => 2_000_000,
            'margin_percent' => 20,
            'margin_bp' => 2000,
            'discount_amount' => 0,
            'tax_bp' => 0,
            'tax_amount' => 0,
            'dp_percent' => 50,
            'total' => 26_400_000,
            'status' => QuotationStatus::Draft,
            'valid_until' => now()->addDays(14),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => QuotationStatus::Sent, 'sent_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Accepted,
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
            'accepted_ip' => '127.0.0.1',
        ]);
    }
}
