<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Customers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_customers', function (Blueprint $table) {
            $table->bigincrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->unsignedBigInteger('phone_number_1')->nullable()->unique();
            $table->unsignedBigInteger('phone_number_2')->nullable()->unique();
            $table->unsignedBigInteger('id_number')->nullable()->unique();
            $table->string('passport_number', 32)->nullable()->unique();
            $table->date('dob')->nullable();
            $table->text('country')->nullable();
            $table->decimal('customer_longitude', 12, 8)->nullable();
            $table->decimal('customer_latitude', 12, 8)->nullable();
            $table->integer('pin')->nullable()->unique();
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
    Schema::dropIfExists('lp_customers');
    }
}
