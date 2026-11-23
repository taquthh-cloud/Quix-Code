<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => 10_000_000,
            'method' => 'manual_transfer',
            'proof_path' => 'payments/'.fake()->uuid().'.jpg',
            'status' => PaymentStatus::Pending,
        ];
    }
}
