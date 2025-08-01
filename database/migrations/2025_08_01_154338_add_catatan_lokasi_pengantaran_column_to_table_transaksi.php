<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCatatanLokasiPengantaranColumnToTableTransaksi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('Transaksi', function (Blueprint $table) {
            $table->string('catatan_lokasi_pengantaran')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('Transaksi', function (Blueprint $table) {
            $table->dropColumn('catatan_lokasi_pengantaran');
        });
    }
}
