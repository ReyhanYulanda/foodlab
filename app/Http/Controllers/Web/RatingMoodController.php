<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RatingMood;
use Illuminate\Http\Request;

class RatingMoodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('read rating_mood');
        $search = $request->input('search');

        $moods = RatingMood::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })
            ->orderBy('order', 'asc')
            ->paginate($request->input('per_page', 10));

        return view('pages.konfigurasi.RatingMood.index', compact('moods'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.konfigurasi.RatingMood.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'order' => 'required|integer|min:0',
        ]);

        RatingMood::create($validated);

        return redirect()->route('rating_mood.index')->with('success', 'Mood berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RatingMood $rating_mood)
    {
        return view('pages.konfigurasi.RatingMood.edit', compact('rating_mood'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RatingMood $rating_mood)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'order' => 'required|integer|min:0',
        ]);

        $rating_mood->update($validated);

        return redirect()->route('rating_mood.index')->with('success', 'Mood berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RatingMood $rating_mood)
    {
        $rating_mood->delete();

        return redirect()->route('rating_mood.index')->with('success', 'Mood berhasil dihapus.');
    }
}
