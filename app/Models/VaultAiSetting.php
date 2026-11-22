<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vault_id
 * @property string $ai_mode
 * @property array<int, string>|null $excluded_paths
 * @property bool $allow_direct_mcp_writes
 * @property bool $allow_direct_inbox_frontmatter
 * @property bool $gardener_enabled
 * @property array<int, string>|null $inbox_folders
 * @property int $gardener_debounce_minutes
 * @property bool $gardener_graph_linker
 * @property bool $gardener_contradiction_check
 * @property string $gardener_schedule
 * @property Carbon|null $last_gardened_at
 * @property string $digest_schedule
 * @property string $digest_folder
 * @property array<string, bool>|null $digest_delivery
 * @property Carbon|null $last_digest_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vault $vault
 */
#[Fillable([
    'vault_id',
    'ai_mode',
    'excluded_paths',
    'allow_direct_mcp_writes',
    'allow_direct_inbox_frontmatter',
    'gardener_enabled',
    'inbox_folders',
    'gardener_debounce_minutes',
    'gardener_graph_linker',
    'gardener_contradiction_check',
    'gardener_schedule',
    'last_gardened_at',
    'digest_schedule',
    'digest_folder',
    'digest_delivery',
    'last_digest_at',
])]
class VaultAiSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'excluded_paths' => 'array',
            'allow_direct_mcp_writes' => 'boolean',
            'allow_direct_inbox_frontmatter' => 'boolean',
            'gardener_enabled' => 'boolean',
            'inbox_folders' => 'array',
            'gardener_debounce_minutes' => 'integer',
            'gardener_graph_linker' => 'boolean',
            'gardener_contradiction_check' => 'boolean',
            'last_gardened_at' => 'datetime',
            'digest_delivery' => 'array',
            'last_digest_at' => 'datetime',
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
     * Determine whether a note path is excluded from server-side AI access and indexing.
     */
    public function isPathExcluded(string $path): bool
    {
        $patterns = $this->excluded_paths ?? [];
        if (empty($patterns)) {
            return false;
        }

        $cleanPath = trim(str_replace('\\', '/', $path), '/');
        if ($cleanPath === '') {
            return false;
        }

        foreach ($patterns as $rawPattern) {
            $pattern = trim(str_replace('\\', '/', (string) $rawPattern), '/');
            if ($pattern === '') {
                continue;
            }

            if (strcasecmp($cleanPath, $pattern) === 0) {
                return true;
            }

            if (str_ends_with($pattern, '/**')) {
                $prefix = substr($pattern, 0, -3);
                if ($prefix !== '' && (strcasecmp($cleanPath, $prefix) === 0 || stripos($cleanPath, $prefix.'/') === 0)) {
                    return true;
                }
            }

            if (stripos($cleanPath, $pattern.'/') === 0) {
                return true;
            }

            if (fnmatch($pattern, $cleanPath, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
