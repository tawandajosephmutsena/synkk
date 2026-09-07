<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vault_id
 * @property int $vault_file_id
 * @property int $chunk_index
 * @property string|null $heading
 * @property int $start_line
 * @property string $content
 * @property int $token_count
 * @property array<int, float> $embedding
 * @property string $content_hash
 * @property array<int, string>|null $wikilinks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vault $vault
 * @property-read VaultFile $file
 */
#[Fillable([
    'vault_id',
    'vault_file_id',
    'chunk_index',
    'heading',
    'start_line',
    'content',
    'token_count',
    'embedding',
    'content_hash',
    'wikilinks',
])]
class VaultFileEmbedding extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'start_line' => 'integer',
            'token_count' => 'integer',
            'embedding' => 'array',
            'wikilinks' => 'array',
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
     * @return BelongsTo<VaultFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id');
    }
}
