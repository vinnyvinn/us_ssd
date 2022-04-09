<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_register_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->string('customer_phone');
            $table->string('customer_name1')->nullable();
            $table->string('customer_name2')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_password')->nullable();
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
        Schema::dropIfExists('lp_register_steps');
    }
}
