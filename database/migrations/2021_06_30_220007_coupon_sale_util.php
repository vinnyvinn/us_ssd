<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CouponSaleUtil extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_coupon_util', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('coupon_id');
            $table->string('user_id');
            $table->string('merchant_id')->nullable();
            $table->string('booking_reference');
            $table->uuid('uuid');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lp_coupon_util');
    }
}
