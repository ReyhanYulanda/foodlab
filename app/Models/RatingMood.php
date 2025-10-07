<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingMood extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'order',
    ];

    public function ratings()
    {
        return $this->belongsToMany(Rating::class, 'detail_mood_rating')
            ->withTimestamps();
    }

    // Scope untuk ambil berdasarkan urutan
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
