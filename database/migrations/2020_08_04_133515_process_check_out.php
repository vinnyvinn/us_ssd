<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProcessCheckOut extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_checkout_session', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('customer_id');
            $table->string('phone_number');
            $table->text('checkout_list')->nullable();
            $table->string('checkout_selection')->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('till_number')->nullable();
            $table->string('short_code')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('checkout_amount')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('lp_checkout_session');
    }
}
