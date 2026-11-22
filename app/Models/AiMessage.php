<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $thread_id
 * @property string $role
 * @property string $content
 * @property array<int, array<string, mixed>>|null $citations
 * @property array<int, array<string, mixed>>|null $tool_calls
 * @property array<int, int>|null $proposal_ids
 * @property string|null $model
 * @property int $tokens_in
 * @property int $tokens_out
 * @property int $duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AiThread $thread
 */
#[Fillable([
    'thread_id',
    'role',
    'content',
    'citations',
    'tool_calls',
    'proposal_ids',
    'model',
    'tokens_in',
    'tokens_out',
    'duration_ms',
])]
class AiMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'tool_calls' => 'array',
            'proposal_ids' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AiThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiThread::class, 'thread_id');
    }
}
