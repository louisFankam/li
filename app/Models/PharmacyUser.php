<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $pharmacy_id
 * @property int $user_id
 * @property string $role
 */
class PharmacyUser extends Pivot
{
    protected $table = 'pharmacy_user';

    public $incrementing = true;

    protected $fillable = ['pharmacy_id', 'user_id', 'role'];
}
