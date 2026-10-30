<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\BranchAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'branch'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['superadmin', 'direktur utama', 'direktur', 'komisaris', 'kacab', 'keuangan', 'akademik', 'marketing', 'operasional', 'staff', 'pengajar'];

    public const CENTRAL_ROLES = ['superadmin', 'direktur utama', 'direktur', 'komisaris'];

    public function canViewAllBranches(): bool
    {
        return $this->hasRole(...self::CENTRAL_ROLES);
    }

    public function canManageMeta(): bool
    {
        return $this->hasRole('kacab', 'marketing') && in_array($this->branch, BranchAccess::BRANCHES, true);
    }

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? route('profile.avatar', [], false).'?v='.$this->updated_at?->getTimestamp() : null;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
