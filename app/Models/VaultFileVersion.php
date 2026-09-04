<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VaultFile $file
 * @property-read Vault $vault
 * @property-read User|null $creator
 */
#[Fillable(['vault_file_id', 'vault_id', 'version', 'storage_path', 'sha256', 'size', 'created_by'])]
class VaultFileVersion extends Model
{
    use HasFactory;

    public function file(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id');
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
