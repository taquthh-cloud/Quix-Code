<?php

namespace Database\Factories;

use App\Models\CaseStudy;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CaseStudy> */
class CaseStudyFactory extends Factory
{
    protected $model = CaseStudy::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'slug' => fake()->unique()->slug(3),
            'title' => ['id' => 'Studi Kasus '.fake()->word(), 'en' => 'Case Study '.fake()->word()],
            'summary' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'tech_stack' => ['Laravel', 'Livewire', 'Tailwind CSS'],
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()]);
    }
}
