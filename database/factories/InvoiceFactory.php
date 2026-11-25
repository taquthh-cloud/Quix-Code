<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'invoice_number' => 'QC/'.now()->year.'/'.fake()->unique()->numerify('#####'),
            'type' => InvoiceType::Dp,
            'currency' => Currency::IDR,
            'amount' => 10_000_000,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => now()->addDays(7),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_amount' => $attributes['amount'] ?? 10_000_000,
            'paid_at' => now(),
            'payment_method' => 'manual_transfer',
        ]);
    }

    public function final(): static
    {
        return $this->state(fn () => ['type' => InvoiceType::Final]);
    }
}
