<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;

class TransaksiDriverController extends Controller
{
    public function TransaksiDriver(Request $request)
    {
        // Ambil persentase biaya ongkir (default 10%)
        $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
        $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 10;

        // Query transaksi
        $query = Transaksi::select(
            'driver_id',
            DB::raw('SUM(ongkos_kirim) as total_ongkir')
        )
            ->whereNotNull('driver_id')
            ->where('status', 'selesai');

        // Filter berdasarkan tanggal
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $data = $query->groupBy('driver_id')
            ->with(['driver.koin'])
            ->get()
            ->map(function ($item) use ($persentasePotongan) {
                $item->pendapatan_pens = $item->total_ongkir * $persentasePotongan / 100;
                $item->pendapatan_driver = $item->total_ongkir - $item->pendapatan_pens;
                $item->saldo_driver = $item->driver->saldo ?? 0;
                return $item;
            });

        return view('pages.transaksi.driver.index', compact('data'));
    }

    public function detailTransaksiDriver(Request $request, $driver_id)
    {
        $driver = User::find($driver_id);

        if (!$driver) {
            return redirect()->back()->with('error', 'Driver tidak ditemukan.');
        }

        $query = Transaksi::where('driver_id', $driver_id)
            ->where('status', 'selesai');

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $transaksi = $query->get();

        return view('pages.transaksi.rincianTransaksiDriver.index', compact('driver', 'transaksi'));
    }

    public function exportTransaksiDriverCsv(Request $request)
    {
        // Ambil persentase biaya ongkir (default 10%)
        $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
        $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 10;

        // Query transaksi (sama seperti halaman index)
        $query = Transaksi::select(
            'driver_id',
            DB::raw('SUM(ongkos_kirim) as total_ongkir')
        )
            ->whereNotNull('driver_id')
            ->where('status', 'selesai');

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $data = $query->groupBy('driver_id')
            ->with(['driver.koin'])
            ->get()
            ->map(function ($item) use ($persentasePotongan) {
                $item->pendapatan_pens = $item->total_ongkir * $persentasePotongan / 100;
                $item->pendapatan_driver = $item->total_ongkir - $item->pendapatan_pens;
                return $item;
            });

        // Hitung rata-rata (yang dipakai hanya pendapatan driver)
        $avg_driver = $data->avg('pendapatan_driver') ?? 0;
        $jumlah_driver = $data->count();

        // Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Judul besar
        $sheet->setCellValue('A1', 'Rekap Pendapatan Driver');
        $sheet->mergeCells('A1:E1');

        // Rata-rata Pendapatan Driver
        $sheet->setCellValue('A2', 'Rata-rata Pendapatan Driver:');
        $sheet->setCellValue('B2', number_format($avg_driver, 0, ',', '.'));

        // Total Driver
        $sheet->setCellValue('A3', 'Total Driver:');
        $sheet->setCellValue('B3', $jumlah_driver);

        // ==============================
        // TABEL DATA DIMULAI BARIS 6
        // ==============================
        $headerRow = 6;

        $headers = ["No", "Nama Driver", "Total Ongkir", "Pendapatan Pens (10%)", "Pendapatan Driver (90%)"];
        $sheet->fromArray($headers, NULL, "A{$headerRow}");

        // Data mulai baris 7
        $row = $headerRow + 1;

        foreach ($data as $index => $item) {
            $sheet->fromArray([
                $index + 1,
                $item->driver->name ?? 'Tidak ditemukan',
                $item->total_ongkir,
                $item->pendapatan_pens,
                $item->pendapatan_driver,
            ], NULL, "A{$row}");
            $row++;
        }

        // Styling header tabel
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => 'center'],
        ];

        $sheet->getStyle("A{$headerRow}:E{$headerRow}")->applyFromArray($headerStyle);

        // Auto size kolom
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // File name
        $fileName = "rekap_driver_" . date('YmdHis') . ".xlsx";

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        ]);
    }
}
