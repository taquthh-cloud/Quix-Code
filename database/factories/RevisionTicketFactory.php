<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\RevisionTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RevisionTicket> */
class RevisionTicketFactory extends Factory
{
    protected $model = RevisionTicket::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => TicketCategory::Revision,
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'sort_order' => 0,
            'created_by' => User::factory()->client(),
        ];
    }
}
