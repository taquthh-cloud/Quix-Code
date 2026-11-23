<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserConsent> */
class UserConsentFactory extends Factory
{
    protected $model = UserConsent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'type' => 'whatsapp_notifications',
            'version' => '1',
            'ip' => fake()->ipv4(),
            'granted_at' => now(),
        ];
    }
}
