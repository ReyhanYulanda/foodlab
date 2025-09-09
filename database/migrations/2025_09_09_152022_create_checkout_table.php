<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheckoutTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checkout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('transaksi_id')->nullable();
            $table->integer('nominal');
            $table->string('status_bayar')->default('pending');
            $table->string('midtrans_request_id')->nullable(); // order_id midtrans
            $table->text('kode_bayar')->nullable(); // url qris
            $table->dateTime('tgl_bayar')->nullable(); // settlement_time
            $table->dateTime('tgl_akhir_tagihan')->nullable(); // expiry_time
            $table->boolean('isTf')->default(false);
            $table->timestamps();
            $table->index('transaksi_id');
            $table->index('midtrans_request_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('checkout');
    }
}
