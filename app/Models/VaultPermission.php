<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vault_id
 * @property int|null $user_id
 * @property string $path
 * @property string $permission
 * @property bool $is_folder
 * @property-read Vault $vault
 * @property-read User|null $user
 */
#[Fillable(['vault_id', 'user_id', 'path', 'permission', 'is_folder'])]
class VaultPermission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->permission) {
            'read_write' => 'Read & Write (Full Sync)',
            'read_only' => 'Read-Only (Download Only)',
            'hidden' => 'Hidden (No Access)',
            default => ucfirst($this->permission),
        };
    }
}
