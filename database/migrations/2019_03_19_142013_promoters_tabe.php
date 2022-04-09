<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PromotersTabe extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_promoters', function (Blueprint $table) {
            $table->bigincrements('id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('user_id');
            $table->text('first_name');
            $table->text('last_name');
            $table->unsignedBigInteger('phone_number')->unique();
            $table->integer('national_id')->nullable();
            $table->binary('image_file')->nullable();
            $table->text('promoter_code');
            $table->longText('promoter_pin');
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
     Schema::dropIfExists('lp_promoters');
    }
}
