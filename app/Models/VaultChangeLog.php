<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vault_id
 * @property int|null $user_id
 * @property string|null $device_name
 * @property string $path
 * @property string $action
 * @property int $version
 * @property string|null $sha256
 * @property int $size
 * @property bool $has_secrets
 * @property array<string>|null $detected_secrets
 * @property Carbon|null $created_at
 * @property-read Vault $vault
 * @property-read User|null $user
 */
#[Fillable(['vault_id', 'user_id', 'device_name', 'path', 'action', 'version', 'sha256', 'size', 'has_secrets', 'detected_secrets'])]
class VaultChangeLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'has_secrets' => 'boolean',
            'detected_secrets' => 'array',
            'created_at' => 'datetime',
        ];
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
}
