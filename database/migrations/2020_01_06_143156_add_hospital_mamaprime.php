<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHospitalMamaprime extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_add_mama_prime', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('customer_number');
            $table->string('outlet_id')->nullable();
            $table->string('hospital_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('product_price')->nullable();
            $table->string('product_deposit')->nullable();
            $table->string('product_payment_instalment')->nullable();
            $table->text('hospital_list')->nullable();
            $table->text('outlet_list')->nullable();
            $table->text('product_list')->nullable();
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
        Schema::dropIfExists('lp_add_mama_prime');
    }
}
