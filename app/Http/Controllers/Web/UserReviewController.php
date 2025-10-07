<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;

class UserReviewController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read user_review');

        $search = $request->query('search');

        $ratings = Rating::with(['user', 'moods'])
            ->when($search, function ($query, $search) {
                $query->where('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10);

        return view('pages.konfigurasi.user_review.index', compact('ratings', 'search'));
    }
}

