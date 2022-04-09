<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{

    use SoftDeletes;
    protected $table = 'lp_coupon';
    protected $fillable = ['coupon_code', 'coupon_amount', 'coupon_status', 'user_id', 'merchant_id', 'uuid'];

}
