<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaultCollaborationDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'path',
        'latest_sequence',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latest_sequence' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<VaultCollaborationUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(VaultCollaborationUpdate::class, 'vault_collaboration_document_id');
    }
}
