<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKolomToTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('no_rekening_toko')->nullable()->after('nama_gambar');
            $table->string('no_rekening_pribadi')->nullable()->after('no_rekening_toko');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['no_rekening_toko', 'no_rekening_pribadi']);
        });
    }
}
