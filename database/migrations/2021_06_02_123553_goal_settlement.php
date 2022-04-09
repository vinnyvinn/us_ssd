<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GoalSettlement extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_goal_settlement', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('user_id');
            $table->string('customer_number');
            $table->string('product_id')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('booking_price')->nullable();
            $table->string('amount_paid')->nullable();
            $table->enum('settlement_status', ['paid', 'unpaid'])->default('unpaid');
            $table->enum('maturity_state', ['active', 'complete', 'overdue'])->default('complete');
            $table->uuid('settlement_tag')->nullable();
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
        //
        Schema::dropIfExists('lp_goal_settlement');
    }
}
