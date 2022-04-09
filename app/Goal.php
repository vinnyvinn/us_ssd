<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{

    protected $table = 'lp_add_chama';
    protected $fillable = ['session_id', 'user_id', 'customer_number', 'outlet_id', 'merchant_id', 'product_id', 'product_description', 'product_price', 'product_deposit', 'product_payment_instalment', 'product_list', 'merchant_list', 'customer_tag'];
}
