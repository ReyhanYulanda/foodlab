<?php

namespace App\Http\Controllers\Masbro;

use App\Http\Controllers\Controller;
use App\Models\TransaksiSaldoKoin;
use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PesananController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read pengantaran')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:diantar,selesai,siap_diantar',
            'gedung' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "Bad Request",
                "message" => $validator->errors()->all()
            ], 400);
        }

        try {
            $transaksi = Transaksi::with(['listTransaksiDetail.menus.tenants', 'user']);

            if ($request->has('status')) {
                if (in_array($request->status, ['diantar', 'selesai'])) {
                    $transaksi = $transaksi->where('driver_id', $user->id);
                }
                $transaksi = $transaksi->where('status', $request->status);
            }

            if ($request->has('gedung')) {
                $transaksi = $transaksi->where('gedung', $request->gedung);
            }

            $transaksi = $transaksi->get();

            return response()->json([
                "status" => "success",
                "message" => "Berhasil mengambil data",
                "data" => [
                    'transaksi' => $transaksi,
                ]
            ]);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }

    public function update(Request $request, $transaksiId, Firebases $firebases)
    {
        $user = $request->user();
        $transaksi = Transaksi::find($transaksiId);
        $cekDriverIsActive = User::where('id', $user->id)->where('isOnline', true)->first();

        if (!$user->can('update pengantaran')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:diantar,selesai,siap_diantar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "Bad Request",
                "message" => $validator->errors()
            ], 400);
        }

        if (!$cekDriverIsActive) {
            return response()->json([
                "status" => "forbidden",
                "message" => "Kamu harus online terlebih dahulu untuk ambil status pesanan"
            ], 403);
        }
        try {
            $transaksi = Transaksi::find($transaksiId);

            if (!$transaksi) {
                return response()->json([
                    "status" => "Not Found",
                    "message" => "Transaksi tidak ditemukan"
                ], 404);
            } else {
                if ($transaksi->status === 'selesai' && $request->status === 'diantar') {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Pesanan sudah selesai"
                    ], 403);
                }

                if ($transaksi->status === 'selesai' && $request->status === 'selesai') {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Pesanan sudah selesai"
                    ], 403);
                }

                if ($transaksi->driver_id !== null && $transaksi->driver_id !== $user->id) {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Transaksi ini sudah memiliki driver"
                    ], 403);
                }

                if ($transaksi->driver_id === null) {
                    $transaksiAktifDriver = Transaksi::where('driver_id', $user->id)
                        ->whereIn('status', ['diantar', 'siap_diantar'])
                        ->count();

                    if ($transaksiAktifDriver >= 5) {
                        return response()->json([
                            "status" => "failed",
                            "message" => "Maksimal 5 pesanan aktif. Selesaikan dulu pengantaran"
                        ], 400);
                    }
                    $transaksi->driver_id = $user->id;
                }

                $transaksi->status = $request->status;
                $transaksi->driver_id = $user->id;
                $transaksi->save();
                $status = str_replace('_', ' ', $transaksi->status);

                $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];




                if ($transaksi->metode_pembayaran != 'transfer') {
                    $transaksi->listTransaksiDetail()->update(['status' => $transaksi->status]);
                }
                if ($transaksi->status == 'diantar') {
                    $firebases
                        ->withNotification('Pesanan Sedang Diantar', "Pesanan {$transaksi->id} sedang diantar oleh driver. Mohon tunggu sebentar!")
                        ->withData([
                            'title' => 'Pesanan Sedang Diantar',
                            'body' => "Pesanan {$transaksi->id} sedang diantar oleh driver. Mohon tunggu sebentar!",
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToFallback($fcmUserToken);
                }


                if ($transaksi->status == 'selesai') {
                    $firebases
                        ->withNotification('Pesanan Selesai', "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽")
                        ->withData([
                            'title' => 'Pesanan Selesai',
                            'body' => "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽",
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToFallback($fcmUserToken);

                    $menu_id = $transaksi->listTransaksiDetail->first()->menus->id ?? null;
                    $tenantUser = User::with('fcmTokens')->whereHas('tenant', function ($tenant) use ($menu_id) {
                        $tenant->whereHas('listMenu', function ($kelola) use ($menu_id) {
                            $kelola->where('id', $menu_id);
                        });
                    })->first();

                    $fcmTenantToken = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                    if (!empty($fcmTenantToken)) {
                        $firebases
                            ->withNotification('Pesanan Selesai', "Pesanan {$transaksi->id} telah diterima oleh pembeli.")
                            ->withData([
                                'title' => 'Pesanan Selesai',
                                'body' => "Pesanan {$transaksi->id} telah diterima oleh pembeli.",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])
                            ->sendToFallback($fcmTenantToken);
                    }

                    $ongkirAsli = $transaksi->ongkos_kirim;

                    $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
                    $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 0;

                    $ongkirAsli = $transaksi->ongkos_kirim;
                    $potongan = ($persentasePotongan / 100) * $ongkirAsli;
                    $ongkirBersih = $ongkirAsli - $potongan;

                    // Simpan ke histori
                    TransaksiSaldoKoin::create([
                        'user_id' => $user->id,
                        'jumlah' => $ongkirBersih,
                        'tipe' => 'masuk',
                        'deskripsi' => "Ongkir dari pesanan #{$transaksi->id}, potongan {$persentasePotongan}% dari {$ongkirAsli}, total masuk: {$ongkirBersih}",
                    ]);

                    // Update saldo user
                    $saldo = SaldoKoin::firstOrCreate(
                        ['user_id' => $user->id],
                        ['jumlah' => 0]
                    );

                    $saldo->jumlah += $ongkirBersih;
                    $saldo->save();
                }

                return response()->json([
                    "status" => "success",
                    "message" => "Pesanan {$request->status}",
                ]);
            }
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }
}
