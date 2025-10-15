<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TransaksiTenantController extends Controller
{
    public function transaksiTenant(Request $request)
    {
        $this->authorize('read transaksi_tenant');

        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $perPage = $request->input('per_page', 10);

        if (!$filterDate && !$startDate && !$endDate) {
            $filterDate = Carbon::today()->toDateString();
        }

        $query = TransaksiDetail::selectRaw("
                tenants.nama_tenant,
                tenants.id,
                SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
                SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
                (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
                (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
            ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($filterDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        } elseif ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($endDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        }

        $transaksiTenant = $query->groupBy('tenants.id', 'tenants.nama_tenant')->paginate($perPage);

        return view('pages.transaksi.tenant.index', compact('transaksiTenant'));
    }

    public function detailTransaksiTenant(Request $request, $id)
    {
        $this->authorize('read transaksi_tenant');

        $perPage = $request->input('per_page', 10);
        $searchKeyword = $request->input('search_keyword');
        $statusPemesan = $request->input('status_pemesan');
        $filterDate = $request->input('filter_date');

        $query = Transaksi::with(['user', 'driver'])
            ->whereHas('listTransaksiDetail.menus', function ($qMenu) use ($id) {
                $qMenu->where('tenant_id', $id);
            })
            ->where('status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($filterDate)->setTime(5, 59, 59);
            $query->whereBetween('updated_at', [$start, $end]);
        }

        if ($searchKeyword) {
            $query->where(function ($q) use ($searchKeyword) {
                $q->where('id', 'like', "%{$searchKeyword}%")
                    ->orWhereHas('user', function ($qUser) use ($searchKeyword) {
                        $qUser->where('name', 'like', "%{$searchKeyword}%");
                    })
                    ->orWhereHas('driver', function ($qDriver) use ($searchKeyword) {
                        $qDriver->where('name', 'like', "%{$searchKeyword}%");
                    });
            });
        }

        if ($statusPemesan) {
            $query->where('isAntar', $statusPemesan === 'antar' ? 1 : 0);
        }

        $transaksiDetails = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return view('pages.transaksi.rincianTransaksiTenant.index', compact('transaksiDetails'));
    }


    public function getPesananByTransaksi($id)
    {
        $transaksi = Transaksi::with('listTransaksiDetail.menus')
            ->select('id', 'catatan_lokasi_pengantaran', 'catatan_penolakan', 'bukti_pengantaran')
            ->findOrFail($id);

        $pesanan = $transaksi->listTransaksiDetail->map(function ($detail) {
            return [
                'nama_menu' => $detail->menus->nama ?? 'Menu Tidak Ditemukan',
                'jumlah'    => $detail->jumlah ?? 0,
                'harga'     => $detail->harga ?? 0,
            ];
        });

        return response()->json([
            'pesanan' => $pesanan,
            'catatan_lokasi_pengantaran' => $transaksi->catatan_lokasi_pengantaran ?? '',
            'catatan_penolakan' => $transaksi->catatan_penolakan ?? '',
            'bukti_pengantaran' => $transaksi->bukti_pengantaran
                ? asset('storage/' . $transaksi->bukti_pengantaran)
                : null,
        ]);
    }


    public function exportCsv(Request $request)
    {
        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
        tenants.nama_tenant,
    tenants.id,
    tenants.no_rekening_toko,
    SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
    SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
    (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - 
     (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
    (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - 
     (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
    ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($filterDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        } elseif ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($endDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        }

        $transaksiTenant = $query
            ->groupBy('tenants.id', 'tenants.nama_tenant', 'tenants.no_rekening_toko')
            ->get();

        $fileName = "mandiri_transfer_" . date('YmdHis') . ".csv";
        $handle = fopen('php://output', 'w');

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($transaksiTenant, $handle) {
            $rekeningSumber = '1400054005005';
            $tanggal = now()->format('Ymd');
            $skipTenants = ['Kedai Pak Agil', 'Test Tenant'];


            $totalBaris = 0;
            $totalAmount = 0;
            $rows = [];

            foreach ($transaksiTenant as $p) {
                if (in_array($p->nama_tenant, $skipTenants)) {
                    continue;
                }

                $namaTenant = str_replace(['"', ','], '', $p->nama_tenant);
                $totalBersih = ($p->pendapatan_bersih_1 ?? 0) + ($p->pendapatan_bersih_2 ?? 0);
                $totalBaris++;
                $totalAmount += $totalBersih;

                $rows[] = [
                    $p->no_rekening_toko ?? 'belum ada rekening',
                    $namaTenant,
                    '',
                    '',
                    '',
                    'IDR',
                    $totalBersih,
                    '',
                    '',
                    'IBU',
                    '',
                    'MANDIRI',
                    'Surabaya',
                    '',
                    '',
                    '',
                    'N',
                    '',
                    '',
                    '',
                    '',
                    'Y',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    'OUR',
                    '1',
                    'E',
                    '',
                    '',
                    '',
                ];
            }

            // Write header row
            fputcsv($handle, [
                'P',
                $tanggal,
                $rekeningSumber,
                $totalBaris,
                $totalAmount
            ]);

            // Write all tenant rows
            foreach ($rows as $row) {
                $cleanedRow = array_map(function ($item) {
                    return str_replace(['"', ','], '', $item); // bersihkan tanda kutip dan koma jika perlu
                }, $row);
                fwrite($handle, implode(',', $cleanedRow) . "\n");
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsvJasa(Request $request)
    {
        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
        tenants.nama_tenant,
    tenants.id,
    tenants.no_rekening_toko,
    SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
    SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
    (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - 
     (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
    (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - 
     (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
    ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($filterDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        } elseif ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->subDay()->setTime(6, 0, 0);
            $end   = Carbon::parse($endDate)->setTime(5, 59, 59);
            $query->whereBetween('transaksi.updated_at', [$start, $end]);
        }

        $transaksiTenant = $query
            ->groupBy('tenants.id', 'tenants.nama_tenant', 'tenants.no_rekening_toko')
            ->get();

        $fileName = "mandiri_transfer_" . date('YmdHis') . ".csv";
        $handle = fopen('php://output', 'w');

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($transaksiTenant, $handle) {
            $rekeningSumber = '1400054005005'; // rekening sumber
            $tanggal = now()->format('Ymd');
            $skipTenants = ['Kedai Pak Agil', 'Test Tenant'];

            $totalAmount = 0;

            foreach ($transaksiTenant as $p) {
                if (in_array($p->nama_tenant, $skipTenants)) {
                    continue;
                }

                $totalBersih = (0.1 * ($p->pendapatan_kotor_1 ?? 0)) + (0.1 * ($p->pendapatan_kotor_2 ?? 0));
                $totalAmount += $totalBersih;
            }

            fputcsv($handle, [
                'P',
                $tanggal,
                $rekeningSumber,
                1,
                $totalAmount
            ]);

            $row = [
                '1400050000257',
                'ubisma',
                '',
                '',
                '',
                'IDR',
                $totalAmount,
                '',
                '',
                'IBU',
                '',
                'MANDIRI',
                'Surabaya',
                '',
                '',
                '',
                'N',
                '',
                '',
                '',
                '',
                'Y',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'OUR',
                '1',
                'E',
                '',
                '',
                '',
            ];

            fwrite($handle, implode(',', $row) . "\n");

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsvRekap(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
                tenants.nama_tenant,
                tenants.id,
                SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
                SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
                SUM(transaksi.ongkos_kirim) as total_ongkir,
                (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
                (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
            ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id');

        $skipTenants = ['Kedai Pak Agil', 'Test Tenant', 'Mie Ayam Ziko'];

        if ($startDate && $endDate) {
            $query->whereBetween('transaksi.updated_at', [$startDate, $endDate]);
        }

        $query->whereNotIn('tenants.nama_tenant', $skipTenants);

        $transaksiTenant = $query->groupBy('menus.tenant_id', 'tenants.nama_tenant')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header tabel
        $headers = ["No", "Nama Tenant", "Pendapatan Kotor (Pesan Antar)", "Ongkir", "Pendapatan Bersih (Pesan Antar)", "Pendapatan Kotor (Ambil Sendiri)", "Pendapatan Bersih (Ambil Sendiri)"];
        $sheet->fromArray($headers, NULL, 'A1');

        $row = 2;
        foreach ($transaksiTenant as $index => $p) {
            $sheet->fromArray([
                $index + 1,
                $p->nama_tenant,
                $p->pendapatan_kotor_1,
                $p->total_ongkir,
                $p->pendapatan_bersih_1,
                $p->pendapatan_kotor_2,
                $p->pendapatan_bersih_2,
            ], NULL, "A{$row}");
            $row++;
        }

        // Auto size kolom
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format angka dengan pemisah ribuan (mulai kolom C sampai G)
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("C2:G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
        // Kalau mau ada Rp di depan, pakai ini:
        // ->setFormatCode('"Rp" #,##0');

        $fileName = "transaksi_tenant_" . date('YmdHis') . ".xlsx";

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        ]);
    }
}
