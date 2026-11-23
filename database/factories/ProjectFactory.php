<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'title' => fake()->randomElement(['Sistem Kasir Toko', 'Aplikasi Rental Mobil', 'Dompet Digital Kampus', 'Portal Klien Agensi']),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Lead,
            'progress_percent' => 0,
            'github_repo' => null,
            'currency' => Currency::IDR,
            'budget_reference' => fake()->numberBetween(10, 50) * 1_000_000,
            'final_price' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Active,
            'progress_percent' => 40,
            'final_price' => 25_000_000,
            'github_repo' => 'quixcode/demo',
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn () => [
            'currency' => Currency::USD,
            'budget_reference' => 150_000, // $1,500.00 dalam sen
        ]);
    }
}
