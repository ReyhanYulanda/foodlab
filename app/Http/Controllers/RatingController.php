<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\Rating;
use App\Models\RatingMood;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function getMoods()
    {
        $moods = RatingMood::orderBy('order', 'asc')->get(['id', 'name', 'order']);

        return response()->json([
            'success' => true,
            'data' => $moods
        ]);
    }

    /**
     * POST /ratings
     * Simpan rating + detail_mood_rating (pivot)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string|max:255',
            'rating_moods' => 'required|array',
            'rating_moods.*' => 'integer|exists:rating_moods,id',
        ]);
        $version = Pengaturan::where('nama', 'version')->first();

        if ($version == null) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found'
            ]);
        }

        $rating = Rating::create([
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'description' => $validated['description'] ?? null,
            'version' => $version->nilai
        ]);


        // Simpan hubungan many-to-many
        $rating->moods()->attach($validated['rating_moods']);

        return response()->json([
            'success' => true,
            'message' => 'Terimakasih telah memberikan penilaian!',
            'data' => [
                'rating' => $rating,
                'moods' => $rating->moods()->get(['rating_moods.id', 'rating_moods.name'])
            ]
        ]);
    }

    public function checkVersion(Request $request)
    {
        $userId = $request->user()->id;
        $version = Pengaturan::where('nama', 'version')->first();

        $exists = Rating::where('user_id', $userId)
            ->where('version', $version->nilai)
            ->exists();

        return response()->json([
            'data' => !$exists
        ]);
    }
}
