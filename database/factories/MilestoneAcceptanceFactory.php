<?php

namespace Database\Factories;

use App\Models\MilestoneAcceptance;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MilestoneAcceptance> */
class MilestoneAcceptanceFactory extends Factory
{
    protected $model = MilestoneAcceptance::class;

    public function definition(): array
    {
        return [
            'milestone_id' => ProjectMilestone::factory(),
            'client_id' => User::factory()->client(),
            'accepted_at' => now(),
            'ip' => fake()->ipv4(),
        ];
    }
}
