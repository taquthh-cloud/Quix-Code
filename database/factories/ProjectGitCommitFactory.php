<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectGitCommit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectGitCommit> */
class ProjectGitCommitFactory extends Factory
{
    protected $model = ProjectGitCommit::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'commit_hash' => fake()->unique()->sha1(),
            'commit_message' => fake()->sentence(5),
            'progress_recorded' => null,
            'pushed_at' => now(),
            'author_email' => fake()->safeEmail(),
        ];
    }
}
