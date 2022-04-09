<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CouponSales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_coupon', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('coupon_code');
            $table->string('user_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->double('coupon_amount', 12)->default(0);
            $table->enum('coupon_status', ['on', 'off'])->default('off');
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
        Schema::dropIfExists('lp_coupon');
    }
}
