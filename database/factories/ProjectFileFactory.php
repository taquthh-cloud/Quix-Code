<?php

namespace Database\Factories;

use App\Enums\FileVisibility;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectFile> */
class ProjectFileFactory extends Factory
{
    protected $model = ProjectFile::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'private',
            'path' => 'files/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'visibility' => FileVisibility::Private,
        ];
    }
}
