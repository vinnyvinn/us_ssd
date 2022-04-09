<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInsuranceSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_add_insurance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('promoter_id');
            $table->string('promoter_phone')->nullable();
            $table->string('outlet_name');
            $table->string('booking_reference')->nullable();
            $table->string('booking_selection')->default(0);
            $table->string('merchant_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_cost')->nullable();
            $table->string('product_serial')->nullable();
            $table->string('product_proposed_premium')->nullable();
            $table->string('product_proposed_premium_days')->nullable();
            $table->string('product_premium_deposit')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('customer_email')->nullable();
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
        Schema::dropIfExists('lp_add_insurance');
    }
}
