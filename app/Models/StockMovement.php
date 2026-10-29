<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['pharmacy_id','product_id','type','quantity','reference_type','reference_id','reason','user_id'];
}
