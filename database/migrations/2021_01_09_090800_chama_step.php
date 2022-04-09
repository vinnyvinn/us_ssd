<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChamaStep extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_add_chama', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('customer_number');
            $table->string('outlet_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('product_description')->nullable();
            $table->string('product_price')->nullable();
            $table->string('product_deposit')->nullable();
            $table->string('product_payment_instalment')->nullable();
            $table->text('product_list')->nullable();
            $table->uuid('customer_tag')->nullable();
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
        Schema::dropIfExists('lp_add_chama');
    }
}
