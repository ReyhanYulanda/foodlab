<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEnumQrisToMetodePembayaranTransaksi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE transaksi 
            MODIFY COLUMN metode_pembayaran 
            ENUM('transfer', 'cod', 'koin', 'qris') NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("
            ALTER TABLE transaksi 
            MODIFY COLUMN metode_pembayaran 
            ENUM('transfer', 'cod', 'koin') NOT NULL
        ");
    }
}
