<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChangeRequest> */
class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'requested',
        ];
    }
}
