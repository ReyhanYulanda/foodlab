<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToTransaksiSaldoKoinTable extends Migration
{
    public function up()
    {
        Schema::table('transaksi_saldo_koin', function (Blueprint $table) {
            $table->softDeletes(); 
        });
    }

    public function down()
    {
        Schema::table('transaksi_saldo_koin', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
