<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id','product_id','quantity_ordered','quantity_received','unit_price'];
}
