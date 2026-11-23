<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationTemplate> */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'locale' => 'id',
            'channel' => 'email',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'version' => 1,
        ];
    }
}
