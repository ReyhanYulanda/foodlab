<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailMoodRating extends Model
{
    use HasFactory;

    protected $table = 'detail_mood_rating';

    protected $fillable = [
        'rating_id',
        'rating_mood_id',
    ];

    public function rating()
    {
        return $this->belongsTo(Rating::class);
    }

    public function mood()
    {
        return $this->belongsTo(RatingMood::class, 'rating_mood_id');
    }
}
