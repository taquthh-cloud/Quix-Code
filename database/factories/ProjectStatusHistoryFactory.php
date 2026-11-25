<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectStatusHistory> */
class ProjectStatusHistoryFactory extends Factory
{
    protected $model = ProjectStatusHistory::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'from' => 'lead',
            'to' => 'quoted',
            'actor_id' => User::factory()->admin(),
            'reason' => null,
            'created_at' => now(),
        ];
    }
}
