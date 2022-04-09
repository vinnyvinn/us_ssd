<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductSteps extends Model
{

    protected $table = 'lp_add_booking';

    protected $fillable = ['id', 'session_id', 'user_id', 'promoter_id',
        'outlet_id', 'merchant_id', 'promoter_phone', 'product_name',
        'product_price', 'product_deposit', 'product_payment_days',
        'customer_number', 'customer_tag', 'product_on_credit',
        'created_at', 'updated_at', 'booking_reference',
        'booking_selection'];


    public static function setProductType($promoter_id)
    {
        $merchant_id = DB::table('lp_promoters')
            ->join('lp_outlets', 'lp_outlets.id', '=', 'lp_promoters.outlet_id')
            ->where('lp_promoters.id', $promoter_id)
            ->value('lp_outlets.merchant_id');

        if (isset($merchant_id)) {
            $hospitals = env('HEALTH_FACILITY_LIST', '85,108,124,103,125');
            if (isset($hospitals)) {
                $hospitals_array = explode(',', $hospitals);

                if (in_array($merchant_id, $hospitals_array)) {
                    $product_type = 'service';
                } else {
                    $product_type = 'product';
                }
            } else {
                $product_type = 'product';
            }
            return $product_type;
        }
        return 'product';
    }
}
