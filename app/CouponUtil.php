<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouponUtil extends Model
{

    use SoftDeletes;
    protected $table = 'lp_coupon_util';
    protected $fillable = ['coupon_id', 'user_id', 'merchant_id', 'booking_reference', 'uuid'];

}
