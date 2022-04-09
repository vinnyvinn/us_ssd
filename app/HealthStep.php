<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HealthStep extends Model
{

    protected $table = 'lp_add_mama_prime';
    protected $fillable = ['session_id', 'user_id', 'customer_number', 'outlet_id', 'hospital_id', 'product_id', 'product_price', 'product_deposit', 'product_payment_instalment', 'hospital_list', 'outlet_list', 'product_list', 'customer_tag'];
}
