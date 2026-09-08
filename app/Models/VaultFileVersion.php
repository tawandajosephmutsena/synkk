<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vault_file_id
 * @property int $vault_id
 * @property int $version
 * @property string $storage_path
 * @property string $sha256
 * @property int $size
 * @property bool $is_encrypted
 * @property string|null $encryption_iv
 * @property string|null $encryption_tag
 * @property bool $is_ghost
 * @property int|null $original_size
 * @property string|null $mime_type
 * @property int $format_version
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VaultFile $file
 * @property-read Vault $vault
 * @property-read User|null $creator
 */
#[Fillable([
    'vault_file_id',
    'vault_id',
    'version',
    'storage_path',
    'sha256',
    'size',
    'created_by',
    'is_encrypted',
    'encryption_iv',
    'encryption_tag',
    'is_ghost',
    'original_size',
    'mime_type',
    'format_version',
])]
class VaultFileVersion extends Model
{
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_ghost' => 'boolean',
            'size' => 'integer',
            'original_size' => 'integer',
            'version' => 'integer',
            'format_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VaultFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
