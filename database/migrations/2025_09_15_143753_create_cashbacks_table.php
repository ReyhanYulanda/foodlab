<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashbacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cashbacks', function (Blueprint $table) {
            $table->id();
            $table->float('value');
            $table->integer('quantity')->default(0);
            $table->boolean('is_valid')->default(true);
            $table->string('referral_code')->unique();
            $table->integer('minimal_beli')->default(0);
            $table->integer('max_cashback')->default(0);
            $table->integer('max_used')->default(1);
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
        Schema::dropIfExists('cashbacks');
    }
}
