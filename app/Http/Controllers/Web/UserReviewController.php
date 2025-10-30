<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Judul header
        $headers = ["No", "Nama User", "Rating (1–10)", "Deskripsi", "Moods", "Tanggal Dibuat"];
        $sheet->fromArray($headers, NULL, 'A1');

        // Isi data
        $row = 2;
        foreach ($ratings as $index => $rating) {
            $sheet->fromArray([
                $index + 1,
                $rating->user->name ?? '-',
                number_format($rating->rating, 1),
                $rating->description ? trim(preg_replace('/\s+/', ' ', $rating->description)) : '-',
                $rating->moods->isNotEmpty() ? $rating->moods->pluck('name')->implode(', ') : '-',
                $rating->created_at ? $rating->created_at->format('d M Y, H:i') : '-',
            ], NULL, "A{$row}");
            $row++;
        }

        // Styling header (bold, background biru, teks putih, tengah)
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'color' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        // Styling isi tabel (border tipis semua kolom)
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => 'AAAAAA'],
                ],
            ],
            'alignment' => [
                'vertical' => 'top',
                'wrapText' => true,
            ],
        ];

        // Terapkan style ke header dan isi tabel
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray($headerStyle);
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->applyFromArray($borderStyle);

        // Auto size kolom
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Nama file export
        $fileName = "user_reviews_" . date('YmdHis') . ".xlsx";

        // Buat writer dan kirim sebagai response
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        ]);
    }
}
