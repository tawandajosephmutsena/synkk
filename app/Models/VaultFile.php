<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $vault_id
 * @property string $path
 * @property string $storage_path
 * @property string $sha256
 * @property int $size
 * @property int $version
 * @property bool $is_deleted
 * @property int|null $last_modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vault $vault
 * @property-read User|null $lastModifier
 */
#[Fillable(['vault_id', 'path', 'storage_path', 'sha256', 'size', 'version', 'is_deleted', 'last_modified_by'])]
class VaultFile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function getDiskPathAttribute(): string
    {
        return Storage::disk('local')->path($this->storage_path);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk('local')->exists($this->storage_path);
    }

    public function getContents(): ?string
    {
        if (! $this->existsOnDisk()) {
            return null;
        }

        return Storage::disk('local')->get($this->storage_path);
    }

    public function isMarkdown(): bool
    {
        return str_ends_with(strtolower($this->path), '.md');
    }
}
