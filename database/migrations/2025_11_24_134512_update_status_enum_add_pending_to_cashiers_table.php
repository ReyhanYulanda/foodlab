<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateStatusEnumAddPendingToCashiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ubah enum status menjadi termasuk 'pending'
        DB::statement("
            ALTER TABLE cashiers 
            MODIFY status ENUM('pending', 'pesanan_diproses', 'selesai') 
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down()
    {
        // Revert enum kembali seperti awal (tanpa pending)
        DB::statement("
            ALTER TABLE cashiers 
            MODIFY status ENUM('pesanan_diproses', 'selesai') 
            NOT NULL DEFAULT 'pesanan_diproses'
        ");
    }
}
