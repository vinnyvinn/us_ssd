<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LoanSessionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_loan_session', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('booking_id');
            $table->string('customer_id');
            $table->string('phone_number')->nullable();
            $table->string('loan_amount')->nullable();
            $table->string('loan_amount_qualified')->nullable();
            $table->enum('loan_status', ['received', 'declined', 'accepted', 'pre_qualified'])->default('received');
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
        Schema::dropIfExists('lp_loan_session');
    }
}
