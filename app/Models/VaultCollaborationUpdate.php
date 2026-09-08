<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultCollaborationUpdate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_collaboration_document_id',
        'sequence',
        'client_update_id',
        'device_token_id',
        'user_id',
        'payload',
        'payload_sha256',
        'is_encrypted',
        'encryption_iv',
        'encryption_tag',
        'format_version',
        'is_checkpoint',
        'acknowledged_base_sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_encrypted' => 'boolean',
            'format_version' => 'integer',
            'is_checkpoint' => 'boolean',
            'acknowledged_base_sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VaultCollaborationDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(VaultCollaborationDocument::class, 'vault_collaboration_document_id');
    }

    /**
     * @return BelongsTo<DeviceToken, $this>
     */
    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
