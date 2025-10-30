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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($ratings) {
            $handle = fopen('php://output', 'w');

            // Tambahkan BOM agar karakter UTF-8 (misal nama dengan aksen) tampil benar di Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header kolom CSV
            fputcsv($handle, [
                'No',
                'Nama User',
                'Rating (1–10)',
                'Deskripsi',
                'Moods',
                'Tanggal Dibuat',
            ]);

            foreach ($ratings as $index => $rating) {
                fputcsv($handle, [
                    $index + 1,
                    $rating->user->name ?? '-',
                    number_format($rating->rating, 1), // tampilkan rating seperti "8.0"
                    $rating->description ? trim(preg_replace('/\s+/', ' ', $rating->description)) : '-', // hilangkan newline
                    $rating->moods->isNotEmpty() ? $rating->moods->pluck('name')->implode(', ') : '-',
                    $rating->created_at ? $rating->created_at->format('d M Y, H:i') : '-',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
