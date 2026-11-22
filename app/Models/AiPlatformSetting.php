<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $managed_ai_kill_switch
 * @property int $default_trial_credits_microcents
 * @property int|null $global_daily_spend_cap_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'managed_ai_kill_switch',
    'default_trial_credits_microcents',
    'global_daily_spend_cap_cents',
])]
class AiPlatformSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'managed_ai_kill_switch' => 'boolean',
            'default_trial_credits_microcents' => 'integer',
            'global_daily_spend_cap_cents' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        return static::query()->create([
            'managed_ai_kill_switch' => false,
            'default_trial_credits_microcents' => (int) config('synkk.ai.credits.trial_microcents', 200_000_000),
            'global_daily_spend_cap_cents' => null,
        ]);
    }
}
