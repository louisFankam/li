<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['supplier_id','pharmacy_id','status','total','ordered_at','received_at'];
    protected $casts = ['total'=>'integer'];
}
