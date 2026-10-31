<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property bool $is_super_admin
 * @property Carbon|null $email_verified_at
 * @property-read Collection<int, Pharmacy> $pharmacies
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Rôles métier reconnus par Permissions::forRole(). */
    public const ROLES = [
        'administrateur',
        'responsable_regional',
        'gerant',
        'pharmacien',
        'vendeur',
        'caissier',
        'gestionnaire_stock',
        'comptable',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Pharmacy, $this, PharmacyUser>
     */
    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacy::class)
            ->using(PharmacyUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function roleFor(?Pharmacy $pharmacy = null): ?string
    {
        $pharmacy ??= $this->currentPharmacy();
        if ($pharmacy === null) {
            return null;
        }

        $pivot = $this->pharmacies()->where('pharmacies.id', $pharmacy->id)->first()?->pivot;

        return $pivot?->role;
    }

    public function currentPharmacy(): ?Pharmacy
    {
        $id = session('active_pharmacy_id');
        if ($id) {
            /** @var Pharmacy|null $pharmacy */
            $pharmacy = $this->pharmacies()->where('pharmacies.id', $id)->first();
            if ($pharmacy) {
                return $pharmacy;
            }
        }

        /** @var Pharmacy|null $fallback */
        $fallback = $this->pharmacies()->first();

        return $fallback;
    }

    public function canAccessPharmacy(?Pharmacy $pharmacy = null): bool
    {
        if ($pharmacy === null) {
            return true;
        }

        if ($this->is_super_admin) {
            return true;
        }

        return $this->pharmacies()->where('pharmacies.id', $pharmacy->id)->exists();
    }

    public function hasPermission(string $permission, ?Pharmacy $pharmacy = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $pharmacy ??= $this->currentPharmacy();
        $role = $this->roleFor($pharmacy);

        if ($role === null) {
            // Tout utilisateur authentifié peut consulter son tableau de bord,
            // même sans affectation à une pharmacie (compte technique).
            return $permission === 'view_dashboard';
        }

        return in_array($permission, Permissions::forRole($role), true);
    }
}
