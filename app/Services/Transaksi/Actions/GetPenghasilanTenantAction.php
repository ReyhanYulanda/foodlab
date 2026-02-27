<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;

class GetPenghasilanTenantAction
{
    public function execute(Request $request)
    {
        $tenantId = $request->user()->id; // ambil id tenant dari user login
        $year = $request->query('year');
        $month = $request->query('month');
        $date = $request->query('date');

        $labels = [];
        $selesaiData = [];
        $refundData = [];
        $transaksiList = [];

        // default: all time
        $dateStart = null;
        $dateEnd = null;

        if ($year && !$month && !$date) {
            // mode yearly → data per bulan
            for ($m = 1; $m <= 12; $m++) {
                $start = Carbon::create($year, $m, 1)->startOfMonth();
                $end = Carbon::create($year, $m, 1)->endOfMonth();

                $labels[] = $start->locale('id')->translatedFormat('F');

                $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'selesai')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();

                $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'refund_selesai')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();
            }

            $dateStart = Carbon::create($year, 1, 1)->startOfYear();
            $dateEnd = Carbon::create($year, 12, 31)->endOfYear();
        } elseif ($year && $month && !$date) {
            // mode monthly → data per minggu (maks 5 minggu)
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

            // 1) Bentuk minggu mentah (Senin–Minggu), lalu clamp ke dalam bulan
            $period = CarbonPeriod::create(
                $startOfMonth->copy()->startOfWeek(Carbon::MONDAY),
                '1 week',
                $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY)
            );

            $rawWeeks = [];
            foreach ($period as $weekStart) {
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

                // Clamp ke bulan
                if ($weekStart < $startOfMonth)
                    $weekStart = $startOfMonth->copy();
                if ($weekEnd > $endOfMonth)
                    $weekEnd = $endOfMonth->copy();

                // Abaikan jika sudah invalid setelah clamp
                if ($weekStart > $weekEnd)
                    continue;

                $rawWeeks[] = [$weekStart, $weekEnd];
            }

            // 2) Normalisasi ke maksimal 5 minggu:
            if (count($rawWeeks) > 5) {
                // Hitung durasi (hari) tiap minggu
                $durations = array_map(function ($range) {
                    [$a, $b] = $range;
                    return $a->diffInDays($b) + 1; // inklusif
                }, $rawWeeks);

                $minIdx = array_keys($durations, min($durations))[0];

                if ($minIdx === 0 && isset($rawWeeks[1])) {
                    // Gabungkan awal → minggu ke-2
                    $rawWeeks[1][0] = $rawWeeks[0][0]->copy();
                    array_splice($rawWeeks, 0, 1);
                } elseif ($minIdx === count($rawWeeks) - 1 && isset($rawWeeks[$minIdx - 1])) {
                    // Gabungkan akhir → minggu sebelumnya
                    $rawWeeks[$minIdx - 1][1] = $rawWeeks[$minIdx][1]->copy();
                    array_splice($rawWeeks, $minIdx, 1);
                } else {
                    // Parsial di tengah: pilih tetangga dengan durasi lebih kecil agar gabungan tetap seimbang
                    $leftDur = $durations[$minIdx - 1] ?? PHP_INT_MAX;
                    $rightDur = $durations[$minIdx + 1] ?? PHP_INT_MAX;

                    if ($rightDur <= $leftDur && isset($rawWeeks[$minIdx + 1])) {
                        // merge ke kanan
                        $rawWeeks[$minIdx + 1][0] = $rawWeeks[$minIdx][0]->copy();
                        array_splice($rawWeeks, $minIdx, 1);
                    } else {
                        // merge ke kiri
                        $rawWeeks[$minIdx - 1][1] = $rawWeeks[$minIdx][1]->copy();
                        array_splice($rawWeeks, $minIdx, 1);
                    }
                }
            }

            // 3) Pakai $rawWeeks (sudah <= 5) sebagai $weekRanges final
            $weekRanges = [];
            $labels = [];
            $selesaiData = [];
            $refundData = [];

            $week = 1;
            foreach ($rawWeeks as [$weekStart, $weekEnd]) {
                $label = "Minggu {$week}";
                $labels[] = $label;
                $weekRanges[$label] = [$weekStart, $weekEnd];

                $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'selesai')
                    ->whereBetween('updated_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
                    ->count();

                $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'refund_selesai')
                    ->whereBetween('updated_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
                    ->count();

                $week++;
            }

            $dateStart = $startOfMonth->copy();
            $dateEnd = $endOfMonth->copy();
        } else {
            // mode harian / rentang custom
            $dateStart = $date ? Carbon::parse($date)->startOfDay() : null;
            $dateEnd = $date ? Carbon::parse($date)->endOfDay() : null;
        }

        // --- Ambil Detail Transaksi untuk Total Pendapatan & List ---
        $queryTransaksi = Transaksi::with(['listTransaksiDetail.menus.tenants'])
            ->whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                $q->where('user_id', $tenantId);
            })
            ->whereIn('status', ['selesai', 'refund_selesai']);

        if ($dateStart && $dateEnd) {
            $queryTransaksi->whereBetween('updated_at', [$dateStart, $dateEnd]);
        }

        $transaksiCollection = $queryTransaksi->orderBy('updated_at', 'desc')->get();

        $totalPendapatan = 0;

        foreach ($transaksiCollection as $trx) {
            // Hitung harga & pendapatan bersih khusus menu milik tenant ini saja di transaksi tsb
            $trxHargaTotal = 0;
            $trxPendapatanBersih = 0;

            foreach ($trx->listTransaksiDetail as $detail) {
                if ($detail->menus && $detail->menus->tenants && $detail->menus->tenants->user_id == $tenantId) {
                    $subtotal = $detail->harga * $detail->jumlah;
                    $trxHargaTotal += $subtotal;
                    $trxPendapatanBersih += ($subtotal - (0.10 * $subtotal)); // -10% admin
                }
            }

            if ($trx->status === 'selesai') {
                $totalPendapatan += $trxPendapatanBersih;
            }

            // Tentukan label (harian, mingguan, bulanan) untuk tabel
            $label = '';
            $label_tanggal = clone $trx->updated_at; // copy instance

            if ($year && !$month && !$date) {
                // yearly -> label nama bulan
                $label = $trx->updated_at->locale('id')->translatedFormat('F');
                $label_tanggal = $trx->updated_at->startOfMonth();
            } elseif ($year && $month && !$date) {
                // monthly -> label Minggu ke-X
                foreach ($weekRanges as $wLabel => [$wStart, $wEnd]) {
                    if ($trx->updated_at->between($wStart->startOfDay(), $wEnd->endOfDay())) {
                        $label = $wLabel;
                        $label_tanggal = clone $wStart;
                        break;
                    }
                }
            } else {
                // harian / default
                $label = $trx->updated_at->format('d/m/Y');
                $label_tanggal = $trx->updated_at->startOfDay();
            }

            $transaksiList[] = [
                'id' => $trx->id,
                'status' => $trx->status,
                'harga' => $trxHargaTotal,
                'pendapatan_bersih' => $trxPendapatanBersih,
                'tanggal' => $trx->updated_at->format('Y-m-d H:i:s'),
                'label' => $label,
                'label_tanggal' => $label_tanggal->format('Y-m-d'), // format seragam untuk sorting nanti
            ];
        }

        return [
            'labels' => $labels,
            'selesaiData' => $selesaiData,
            'refundData' => $refundData,
            'totalSelesai' => array_sum($selesaiData),
            'totalRefund' => array_sum($refundData),
            'totalPendapatan' => $totalPendapatan,
            'transaksiList' => $transaksiList,
            'year' => $year,
            'month' => $month,
            'date' => $date
        ];
    }
}
