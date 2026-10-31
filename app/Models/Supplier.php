<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 */
class Supplier extends Model
{
    protected $fillable = ['name', 'contact', 'phone', 'email', 'address'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
