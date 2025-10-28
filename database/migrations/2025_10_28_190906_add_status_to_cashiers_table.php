<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToCashiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cashiers', function (Blueprint $table) {
            Schema::table('cashiers', function (Blueprint $table) {
                $table->enum('status', ['pesanan_diproses', 'selesai'])
                    ->default('pesanan_diproses')
                    ->after('total');
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cashiers', function (Blueprint $table) {
            Schema::table('cashiers', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        });
    }
}
