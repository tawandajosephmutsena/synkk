<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $team = \App\Models\Team::create([
            'name' => 'Demo Team',
            'slug' => 'demo-team',
            'is_personal' => true,
        ]);

        $team->members()->attach($user, ['role' => 'owner']);

        $user->forceFill(['current_team_id' => $team->id])->save();

        $vault = \App\Models\Vault::create([
            'team_id' => $team->id,
            'name' => 'Demo Vault',
            'slug' => 'demo-vault',
            'description' => 'Local test vault for Obsidian sync',
            'default_permission' => 'read_write',
            'created_by' => $user->id,
        ]);

        // Known local testing token: synkk_demo_token_123456789012345678901234567890
        $plainToken = 'synkk_demo_token_123456789012345678901234567890';
        \App\Models\DeviceToken::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => 'Obsidian Desktop (Local)',
            'token_hash' => hash('sha256', $plainToken),
            'token_preview' => substr($plainToken, 0, 12).'...',
            'client_platform' => 'mac',
        ]);
    }
}
