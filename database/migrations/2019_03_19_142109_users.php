<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Users extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_users', function (Blueprint $table) {
            $table->bigincrements('id');
            $table->string('email', 50)->unique()->nullable();
            $table->string('password', 255)->nullable();
            $table->unsignedBigInteger('user_type');
            $table->boolean('is_verified');
            $table->rememberToken();
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
      Schema::dropIfExists('lp_users');
    }
}
