<?php

namespace Database\Factories;

use App\Models\TeamMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMessage>
 */
class TeamMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => \App\Models\Team::factory(),
            'user_id' => \App\Models\User::factory(),
            'author_name' => fake()->name(),
            'body' => fake()->sentence(),
            'type' => 'chat',
            'read_at' => null,
        ];
    }
}
