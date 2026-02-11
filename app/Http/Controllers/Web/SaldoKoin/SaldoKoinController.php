<?php

namespace App\Http\Controllers\Web\SaldoKoin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SaldoKoinController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read saldo_koin');

        $perPage = $request->input('per_page', 10);
        $query = SaldoKoin::with('user');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        $saldos = $query->paginate($perPage);

        return view('pages.saldoKoin.index', compact('saldos'));
    }

    public function create()
    {
        $this->authorize('create saldo_koin');

        $users = User::select('id', 'name', 'email')->get();
        return view('pages.saldoKoin.create', compact('users'));
    }

    public function store(Request $request, Firebases $firebases)
    {
        $this->authorize('create saldo_koin');

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'jumlah' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string|max:255'
        ]);

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $request->user_id]);

        $saldo->jumlah += $request->jumlah;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $request->user_id,
            'jumlah' => $request->jumlah,
            'tipe' => 'masuk',
            'deskripsi' => $request->deskripsi ?? 'Penambahan Saldo Koin'
        ]);

        $user = User::with('fcmTokens')->find($request->user_id);
        $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

        if ($user && $user->fcm_token) {
            $firebases
                ->withNotification('Top-up Berhasil', 'Saldo sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' telah ditambahkan ke akun Anda.')
                ->withData([
                    'title' => 'Top-up Berhasil',
                    'body' => 'Saldo sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' telah ditambahkan ke akun Anda.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])->sendToFallback($fcmUserToken);
        }

        return redirect()->route('saldoKoin.index')->with('success', 'Saldo koin berhasil diperbarui.');
    }

    public function riwayatTransaksi($user_id)
    {
        $transaksi = TransaksiSaldoKoin::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.saldoKoin.riwayat', compact('transaksi'));
    }

    public function exportCsv()
    {
        $this->authorize('read saldo_koin');

        $saldos = SaldoKoin::with('user')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $headers = ['No', 'Nama User', 'Email', 'Jumlah Saldo', 'Terakhir Diperbarui'];
        $sheet->fromArray($headers, null, 'A1');

        // Data rows
        $row = 2;
        foreach ($saldos as $index => $saldo) {
            $sheet->fromArray([
                $index + 1,
                $saldo->user->name ?? '-',
                $saldo->user->email ?? '-',
                $saldo->jumlah ?? 0,
                $saldo->updated_at ? $saldo->updated_at->format('d-m-Y H:i:s') : '-',
            ], null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format angka pada kolom Jumlah Saldo (D)
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("D2:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        $fileName = 'saldo_koin_' . date('YmdHis') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
