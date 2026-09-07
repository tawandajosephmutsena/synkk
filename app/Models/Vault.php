<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $default_permission
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read Collection<int, VaultFile> $files
 * @property-read Collection<int, VaultPermission> $permissions
 * @property-read Collection<int, VaultChangeLog> $changeLogs
 * @property-read Collection<int, VaultFileEmbedding> $embeddings
 */
#[Fillable(['team_id', 'name', 'slug', 'description', 'default_permission', 'created_by', 'is_e2ee', 'e2ee_salt', 'e2ee_test_cipher'])]
class Vault extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_e2ee' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Vault $vault) {
            if (empty($vault->slug)) {
                $vault->slug = Str::slug($vault->name);
                $originalSlug = $vault->slug;
                $count = 1;

                while (static::where('team_id', $vault->team_id)->where('slug', $vault->slug)->exists()) {
                    $vault->slug = "{$originalSlug}-{$count}";
                    $count++;
                }
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<VaultFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(VaultFile::class);
    }

    /**
     * @return HasMany<VaultPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(VaultPermission::class);
    }

    /**
     * @return HasMany<VaultChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(VaultChangeLog::class);
    }

    /**
     * @return HasMany<VaultFileVersion, $this>
     */
    public function fileVersions(): HasMany
    {
        return $this->hasMany(VaultFileVersion::class);
    }

    /**
     * @return HasMany<VaultFileEmbedding, $this>
     */
    public function embeddings(): HasMany
    {
        return $this->hasMany(VaultFileEmbedding::class);
    }

    public function latestVersion(): int
    {
        return (int) ($this->files()->max('version') ?? 0);
    }

    public function totalStorageBytes(): int
    {
        return (int) $this->files()->where('is_deleted', false)->sum('size');
    }

    /**
     * Resolve effective permission for a given user on a given path.
     * Returns: 'read_write', 'read_only', or 'hidden'
     */
    public function permissionForPath(?User $user, string $path): string
    {
        if (! $user) {
            return 'hidden';
        }

        // Team Owner and Admin always have full read_write on all vaults
        $user->loadMissing('teamMemberships');
        $userRole = $user->teamMemberships
            ->firstWhere('team_id', $this->team_id)
            ?->role;

        if ($userRole === TeamRole::Owner || $userRole === TeamRole::Admin) {
            return 'read_write';
        }

        // Normalize path without leading/trailing slashes
        $cleanPath = trim($path, '/');

        // Reuse one rule snapshot while resolving every file in this request.
        $this->loadMissing('permissions');
        $rules = $this->permissions->filter(
            fn (VaultPermission $rule): bool => $rule->user_id === null || $rule->user_id === $user->id,
        );

        // Find matching rules
        $matching = $rules->filter(function (VaultPermission $rule) use ($cleanPath) {
            $rulePath = trim($rule->path, '/');

            // Exact match
            if ($rulePath === $cleanPath) {
                return true;
            }

            // Folder match (if is_folder or path ends with slash or matched as prefix)
            if ($rule->is_folder || str_contains($rule->path, '/')) {
                if (str_starts_with($cleanPath, $rulePath.'/')) {
                    return true;
                }
            }

            return false;
        });

        if ($matching->isNotEmpty()) {
            // Priority 1: User-specific rules over global team rules
            // Priority 2: Most specific (longest) path rule
            $best = $matching->sort(function (VaultPermission $a, VaultPermission $b) {
                $aUser = $a->user_id ? 1 : 0;
                $bUser = $b->user_id ? 1 : 0;

                if ($aUser !== $bUser) {
                    return $bUser <=> $aUser;
                }

                return strlen($b->path) <=> strlen($a->path);
            })->first();

            return $best->permission;
        }

        return $this->default_permission ?: 'read_write';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
