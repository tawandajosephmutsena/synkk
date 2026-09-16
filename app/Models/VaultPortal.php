<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $vault_id
 * @property int $created_by
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string|null $description
 * @property string $layout
 * @property string $theme
 * @property string $font_family
 * @property string $accent_color
 * @property bool $is_public
 * @property string|null $password_hash
 * @property string $root_path
 * @property int|null $primary_file_id
 * @property array<string, mixed>|null $settings
 * @property int $views_count
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Vault $vault
 * @property-read User $creator
 * @property-read VaultFile|null $primaryFile
 */
#[Fillable([
    'team_id',
    'vault_id',
    'created_by',
    'name',
    'slug',
    'domain',
    'description',
    'layout',
    'theme',
    'font_family',
    'accent_color',
    'is_public',
    'password',
    'password_hash',
    'root_path',
    'primary_file_id',
    'settings',
    'views_count',
    'last_accessed_at',
])]
class VaultPortal extends Model
{
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'settings' => 'array',
            'views_count' => 'integer',
            'last_accessed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VaultPortal $portal) {
            if (empty($portal->slug)) {
                $portal->slug = Str::slug($portal->name);
                $originalSlug = $portal->slug;
                $count = 1;

                while (static::where('slug', $portal->slug)->exists()) {
                    $portal->slug = "{$originalSlug}-{$count}";
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

    /**
     * @return BelongsTo<VaultFile, $this>
     */
    public function primaryFile(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'primary_file_id');
    }

    public function isPasswordProtected(): bool
    {
        return ! empty($this->password_hash);
    }

    public function verifyPassword(string $password): bool
    {
        if (empty($this->password_hash)) {
            return true;
        }

        return Hash::check($password, $this->password_hash);
    }

    public function setPassword(?string $password): void
    {
        $this->password_hash = filled($password) ? Hash::make($password) : null;
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->password_hash = filled($value) ? Hash::make($value) : null;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * @return Collection<int, VaultFile>
     */
    public function getAccessibleFiles(): Collection
    {
        $query = VaultFile::query()
            ->where('vault_id', $this->vault_id)
            ->where('is_deleted', false);

        if ($this->root_path !== '/' && filled($this->root_path)) {
            $prefix = trim($this->root_path, '/');
            $query->where(function ($q) use ($prefix) {
                $q->where('path', $prefix)
                    ->orWhere('path', 'like', "{$prefix}/%");
            });
        }

        return $query->orderBy('path', 'asc')->get();
    }

    public function incrementViews(): void
    {
        $this->timestamps = false;
        $this->increment('views_count');
        $this->update(['last_accessed_at' => now()]);
        $this->timestamps = true;
    }

    public function getUrl(): string
    {
        return url("/p/{$this->slug}");
    }

    /**
     * Returns color tokens, backgrounds, and accents for the active theme.
     *
     * @return array<string, string>
     */
    public function getThemePalette(): array
    {
        return match ($this->theme) {
            'slate-luxe' => [
                'bg' => 'bg-slate-950 text-slate-100',
                'card' => 'bg-slate-900/80 border-slate-800 text-slate-100 backdrop-blur-md',
                'accent' => 'text-indigo-400 bg-indigo-500/10 border-indigo-500/30',
                'accent_button' => 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-indigo-500/20 shadow-lg',
                'border' => 'border-slate-800',
                'muted' => 'text-slate-400',
            ],
            'midnight-emerald' => [
                'bg' => 'bg-neutral-950 text-emerald-50',
                'card' => 'bg-neutral-900/90 border-emerald-500/20 text-emerald-50 backdrop-blur-md',
                'accent' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
                'accent_button' => 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-500/20 shadow-lg',
                'border' => 'border-emerald-500/20',
                'muted' => 'text-emerald-400/60',
            ],
            'paper-craft' => [
                'bg' => 'bg-[#FBFBFA] text-stone-900',
                'card' => 'bg-white border-stone-200 text-stone-900 shadow-sm',
                'accent' => 'text-amber-800 bg-amber-100 border-amber-300',
                'accent_button' => 'bg-stone-900 hover:bg-stone-800 text-white shadow-sm',
                'border' => 'border-stone-200',
                'muted' => 'text-stone-500',
            ],
            'amber-gold' => [
                'bg' => 'bg-zinc-950 text-amber-50',
                'card' => 'bg-zinc-900/80 border-amber-500/20 text-zinc-100 backdrop-blur-md',
                'accent' => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
                'accent_button' => 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold shadow-amber-500/20 shadow-lg',
                'border' => 'border-amber-500/20',
                'muted' => 'text-amber-300/60',
            ],
            default => [ // obsidian-noir
                'bg' => 'bg-[#09090b] text-zinc-100',
                'card' => 'bg-[#121215] border-zinc-800 text-zinc-100',
                'accent' => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
                'accent_button' => 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-semibold shadow-amber-500/20 shadow-lg',
                'border' => 'border-zinc-800',
                'muted' => 'text-zinc-400',
            ],
        };
    }
}
