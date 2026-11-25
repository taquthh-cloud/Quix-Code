<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectNote> */
class ProjectNoteFactory extends Factory
{
    protected $model = ProjectNote::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory()->admin(),
            'body' => fake()->paragraph(),
        ];
    }
}
