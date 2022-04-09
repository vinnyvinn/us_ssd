<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('lp_add_booking', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('promoter_id');
            $table->string('outlet_id');
            $table->string('booking_reference')->nullable();
            $table->string('booking_selection')->default(0);
            $table->string('merchant_id')->nullable();
            $table->string('promoter_phone')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_price')->nullable();
            $table->string('product_deposit')->nullable();
            $table->string('product_payment_days')->nullable();
            $table->string('customer_number')->nullable();
            $table->uuid('customer_tag');
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
        Schema::dropIfExists('lp_add_booking');
    }
}
