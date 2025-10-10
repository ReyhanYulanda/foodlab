<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rating',
        'description',
        'version',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moods()
    {
        return $this->belongsToMany(
            RatingMood::class,
            'detail_mood_rating',
            'rating_id',
            'rating_mood_id'
        );
    }
}
