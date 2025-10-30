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

    public function exportCsv(Request $request)
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
            ->get();

        $filename = 'user_reviews_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($ratings) {
            $handle = fopen('php://output', 'w');

            // Header kolom
            fputcsv($handle, ['No', 'Nama User', 'Rating', 'Deskripsi', 'Moods', 'Tanggal']);

            foreach ($ratings as $index => $rating) {
                fputcsv($handle, [
                    $index + 1,
                    $rating->user->name ?? 'Tidak diketahui',
                    $rating->rating,
                    $rating->description,
                    $rating->moods->pluck('name')->implode(', '),
                    $rating->created_at->format('d-m-Y H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
