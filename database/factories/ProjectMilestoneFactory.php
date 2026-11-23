<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMilestone> */
class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->words(3, true),
            'target_progress' => fake()->randomElement([25, 50, 75, 100]),
            'status' => MilestoneStatus::Pending,
            'due_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'sort_order' => 0,
        ];
    }
}
