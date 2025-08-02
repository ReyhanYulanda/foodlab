<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopUpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('top_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('request_id');
            $table->integer('nominal');
            $table->string('status_bayar')->default('0');
            $table->string('kode_bayar')->nullable();
            $table->datetime('tgl_bayar')->nullable();
            $table->dateTime('tgl_akhir_tagihan')->nullable();
            $table->boolean('isTf')->default(false);
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
        Schema::dropIfExists('top_ups');
    }
}
