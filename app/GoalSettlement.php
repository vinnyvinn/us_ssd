<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoalSettlement extends Model
{

    use  SoftDeletes;
    protected $table = 'lp_goal_settlement';
    protected $fillable = ['session_id', 'user_id', 'customer_number', 'product_id', 'booking_id', 'booking_reference','booking_price', 'amount_paid','maturity_state','settlement_status'];
}
