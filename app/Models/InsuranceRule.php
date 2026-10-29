<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceRule extends Model
{
    protected $fillable = ['insurance_company_id','type','value','category_id','product_id'];
}
