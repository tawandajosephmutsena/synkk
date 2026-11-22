<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $thread_id
 * @property int $vault_id
 * @property int $user_id
 * @property string $status
 * @property string|null $partial_content
 * @property string|null $status_note
 * @property int|null $assistant_message_id
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AiThread $thread
 * @property-read Vault $vault
 * @property-read User $user
 * @property-read AiMessage|null $assistantMessage
 */
#[Fillable([
    'thread_id',
    'vault_id',
    'user_id',
    'status',
    'partial_content',
    'status_note',
    'assistant_message_id',
    'error_message',
])]
class AiRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<AiThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<Vault, $this>
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiMessage, $this>
     */
    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'assistant_message_id');
    }
}
