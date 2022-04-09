<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoalFlex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_add_goal_flex_step', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('customer_number');
            $table->string('product_name')->nullable();
            $table->string('merchant_price')->default(0);
            $table->string('flexpay_price')->nullable();
            $table->string('product_booking_days')->nullable();
            $table->string('product_type')->nullable();
            $table->string('product_attribute')->nullable();
            $table->uuid('uuid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('lp_add_goal_flex_step');
    }
}
