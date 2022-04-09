<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Products extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lp_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('merchant_id')->default(0)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('promoter_id')->nullable();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('product_name');
            $table->string('product_code',100)->unique();
            $table->string('product_short_description')->nullable();
            $table->string('product_long_description')->nullable();
            $table->longText('product_images')->nullable();
            $table->string('product_attribute')->nullable();
            $table->integer('product_booking_days');
            $table->float('merchant_price', 10, 2);
            $table->float('flexpay_price', 10, 2);
            $table->float('product_discount', 7, 2)->nullable();
            $table->enum('mode', ['true', 'false'])->default('true');
            $table->string('type');
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
        Schema::dropIfExists('lp_products');
    }
}
