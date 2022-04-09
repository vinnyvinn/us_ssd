<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FlexpayPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_payment_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('session_id');
            $table->string('customer_phone');
            $table->string('payment_reference')->nullable();
            $table->string('payment_list')->nullable();
            $table->integer('payment_selection')->nullable();
            $table->string('payment_amount')->nullable();
            $table->enum('payment_status',['initializePay','confirmOnly'])->default(null);
            $table->enum('session_status',['open','closed'])->default('open');
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
       Schema::dropIfExists('lp_payment_steps');
    }
}
