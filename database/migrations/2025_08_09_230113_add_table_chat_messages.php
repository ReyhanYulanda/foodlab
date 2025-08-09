<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTableChatMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaksi_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->timestamps();
            // Relasi ke transaksi
            $table->foreign('transaksi_id')
                ->references('id')
                ->on('transaksi')
                ->onDelete('cascade');

            // Relasi ke users (pengirim pesan)
            $table->foreign('sender_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Index untuk optimasi pencarian
            $table->index(['transaksi_id', 'sender_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chat_messages');
    }
}
