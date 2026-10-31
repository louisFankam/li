<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_active
 * @property-read PharmacyUser|null $pivot
 */
class Pharmacy extends Model
{
    protected $fillable = ['name', 'city', 'address', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * @return BelongsToMany<User, $this, PharmacyUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(PharmacyUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_pharmacy_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_pharmacy_id');
    }
}
