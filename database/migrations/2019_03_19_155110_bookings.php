<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Bookings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_booking', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('product_id');
            $table->bigInteger('user_id');
            $table->bigInteger('merchant_id');
            $table->bigInteger('promoter_id');
            $table->string('booking_reference');
            $table->float('booking_price', 32);
            $table->float('booking_offer_price', 32);
            $table->float('initial_deposit', 32);
            $table->enum('has_fixed_deadline', [0, 1])->default(0);
            $table->enum('booking_status', ['open', 'closed'])->default('open');
            $table->date('end_date');
            $table->string('invoice_id')->nullable();
            $table->string('booking_description')->nullable();
            $table->string('booking_description_code')->nullable();
            $table->enum('paid_out', ['no', 'yes'])->default('no');
            $table->date('deadline_date');
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
       Schema::dropIfExists('product_booking');
    }
}
