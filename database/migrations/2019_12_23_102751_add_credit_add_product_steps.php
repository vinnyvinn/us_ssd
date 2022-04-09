<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditAddProductSteps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lp_add_booking', function (Blueprint $table) {
            $table->enum('product_on_credit', ['0', '1'])->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lp_add_booking', function (Blueprint $table) {
            $table->dropColumn('product_on_credit');
        });
    }
}
