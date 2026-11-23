<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookDelivery> */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'delivery_id' => fake()->uuid(),
            'event' => 'push',
            'status' => 'received',
            'payload' => ['ref' => 'refs/heads/main', 'commits' => []],
        ];
    }
}
