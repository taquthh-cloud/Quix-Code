<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationLog> */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => 'email',
            'template' => 'progress_updated',
            'status' => 'queued',
        ];
    }
}
