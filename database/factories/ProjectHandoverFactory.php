<?php

namespace Database\Factories;

use App\Enums\HandoverMethod;
use App\Models\Project;
use App\Models\ProjectHandover;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectHandover> */
class ProjectHandoverFactory extends Factory
{
    protected $model = ProjectHandover::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'delivery_method' => HandoverMethod::Download,
            'payload' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
