<?php

namespace Database\Factories;

use App\Models\OrderChat;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderChat> */
class OrderChatFactory extends Factory
{
    protected $model = OrderChat::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sender_id' => User::factory(),
            'message' => fake()->sentence(),
        ];
    }
}
