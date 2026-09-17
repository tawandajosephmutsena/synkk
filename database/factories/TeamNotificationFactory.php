<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamNotification>
 */
class TeamNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'type' => 'info',
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'action_url' => null,
            'read_at' => null,
        ];
    }
}
