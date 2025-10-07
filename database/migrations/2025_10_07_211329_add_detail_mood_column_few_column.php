<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetailMoodColumnFewColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('detail_mood_rating', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_mood_rating', 'rating_id')) {
                $table->foreignId('rating_id')->constrained('ratings')->onDelete('cascade');
            }

            if (!Schema::hasColumn('detail_mood_rating', 'rating_mood_id')) {
                $table->foreignId('rating_mood_id')->constrained('rating_moods')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('detail_mood_rating', function (Blueprint $table) {
            $table->dropForeign(['rating_id']);
            $table->dropForeign(['rating_mood_id']);
            $table->dropColumn(['rating_id', 'rating_mood_id']);
        });
    }
}
