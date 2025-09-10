<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBiayaAdminToCheckoutTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkout', function (Blueprint $table) {
            $table->integer('biaya_midtrans')
                ->nullable()
                ->after('nominal');

            $table->integer('biaya_ubisma')
                ->nullable()
                ->after('biaya_midtrans');

            $table->integer('total_biaya_admin')
                ->nullable()
                ->after('biaya_ubisma');

            $table->integer('total_bayar_user')
                ->nullable()
                ->after('total_biaya_admin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('checkout', function (Blueprint $table) {
            $table->dropColumn(['biaya_midtrans', 'biaya_ubisma', 'total_biaya_admin', 'total_bayar_user']);
        });
    }
}
