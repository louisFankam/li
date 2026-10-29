<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id','pharmacy_id','action','subject_type','subject_id','old_values','new_values','ip'];
    protected $casts = ['old_values'=>'array','new_values'=>'array'];
}
