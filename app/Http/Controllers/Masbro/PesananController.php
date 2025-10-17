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
use Intervention\Image\Facades\Image;

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

            // ✅ Handle conditional status
            if ($request->has('status')) {

                // === CASE: status = siap_diantar ===
                if ($request->status === 'siap_diantar') {
                    $transaksi = $transaksi->where(function ($q) use ($user) {
                        $q->where('status', 'siap_diantar')
                            ->orWhere(function ($sub) use ($user) {
                                $sub->where('isPriority', 1)
                                    ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses'])
                                    ->where(function ($sub2) use ($user) {
                                        $sub2->whereNull('driver_id')
                                            ->orWhere('driver_id', $user->id);
                                    });
                            });
                    });
                }

                // === CASE: status = diantar / selesai ===
                elseif (in_array($request->status, ['diantar', 'selesai'])) {
                    $transaksi = $transaksi->where('driver_id', $user->id)
                        ->where('status', $request->status);
                }

                // === CASE: status lainnya ===
                else {
                    $transaksi = $transaksi->where('status', $request->status);
                }
            }

            // Optional filter gedung
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
        $status = $request->query('status') ?? $request->input('status');

        // merge biar konsisten
        $request->merge(['status' => $status]);

        // validasi semua field
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pesanan_diproses,diantar,selesai,siap_diantar',
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

        // if ($request->status === 'selesai') {
        //     if ($request->hasFile('bukti_pengantaran')) {
        //         $file = $request->file('bukti_pengantaran');
        //         $path = $file->store('bukti_pengantaran', 'public');
        //     } else {
        //         return response()->json([
        //             "status" => "Bad Request",
        //             "message" => "Upload bukti pengantaran"
        //         ], 400);
        //     }
        // }

        try {
            $transaksi = Transaksi::find($transaksiId);

            if (!$transaksi) {
                return response()->json([
                    "status" => "Not Found",
                    "message" => "Transaksi tidak ditemukan"
                ], 404);
            }
            if ($status === 'pesanan_diproses') {
                if ($transaksi->isPriority == 1) {
                    if ($transaksi->status === 'pesanan_masuk' || $transaksi->status === 'pesanan_diproses') {
                        if (in_array($transaksi->status, ['refund_selesai', 'selesai'])) {
                            return response()->json([
                                "status" => "forbidden",
                                "message" => "Pesanan sudah selesai atau direfund, tidak bisa diambil lagi",
                            ], 403);
                        }

                        if ($transaksi->status === 'pesanan_masuk' && $request->status === 'pesanan_diproses') {
                            // assign driver id
                            if ($transaksi->driver_id === null) {
                                $transaksi->driver_id = $user->id;
                                $transaksi->save();

                                // send notification
                                $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                                $firebases
                                    ->withNotification('Pesanan Telah mendapatkan driver', "Pesanan {$transaksi->id} telah mendapatkan driver. Mohon tunggu tenant menyiapkan pesanan!")
                                    ->withData([
                                        'title' => 'Pesanan Telah mendapatkan driver',
                                        'body' => "Pesanan {$transaksi->id} telah mendapatkan driver. Mohon tunggu tenant menyiapkan pesanan!",
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    ])->sendToFallback($fcmUserToken);


                                return response()->json([
                                    "status" => "success",
                                    "message" => "Driver berhasil ditetapkan ke pesanan prioritas tanpa mengubah status",
                                    "data" => $transaksi
                                ]);
                            }
                            if ($transaksi->driver_id !== null) {
                                if ($transaksi->driver_id !== $user->id) {
                                    return response()->json([
                                        "status" => "forbidden",
                                        "message" => "Pesanan prioritas ini sudah diambil oleh driver lain",
                                    ], 403);
                                }
                                $transaksi->status = 'pesanan_diproses';
                                $transaksi->save();
                            }
                            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                            $firebases
                                ->withNotification('Pesanan sedang diproses oleh tenant', "Pesanan {$transaksi->id} sedang diproses oleh tenant. Mohon tunggu tenant menyiapkan pesanan!")
                                ->withData([
                                    'title' => 'Pesanan sedang diproses oleh tenant',
                                    'body' => "Pesanan {$transaksi->id} sedang diproses oleh tenant. Mohon tunggu tenant menyiapkan pesanan!",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])->sendToFallback($fcmUserToken);

                            return response()->json([
                                "status" => "success",
                                "message" => "Driver berhasil mengubah status pesanan masuk ke pesanan diproses",
                                "data" => $transaksi,
                            ]);
                        }

                        if ($transaksi->driver_id !== $user->id) {
                            return response()->json([
                                "status" => "forbidden",
                                "message" => "Pesanan prioritas ini sudah diambil oleh driver lain",
                            ], 403);
                        }

                        return response()->json([
                            "status" => "success",
                            "message" => "Pesanan prioritas sudah Anda ambil sebelumnya",
                        ]);
                    }
                    // if ($transaksi->status === 'siap_diantar') {
                    //     $transaksi->driver_id = $user->id;
                    //     $transaksi->status = 'diantar';
                    //     $transaksi->save();

                    //     $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                    //     $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                    //     $firebases
                    //         ->withNotification('Pesanan Telah mendapatkan driver', "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!")
                    //         ->withData([
                    //             'title' => 'Pesanan Telah mendapatkan driver',
                    //             'body' => "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!",
                    //             'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    //         ])->sendToFallback($fcmUserToken);

                    //     return response()->json([
                    //         "status" => "success",
                    //         "message" => "Driver berhasil ditetapkan ke pesanan prioritas",
                    //         "data" => $transaksi
                    //     ]);
                    // }
                }
            }
            if ($status === 'diantar') {
                // Jika pesanan prioritas
                if ($transaksi->isPriority == 1) {
                    if ($transaksi->status === 'pesanan_diproses' & $request->status === 'diantar' & $transaksi->driver_id === null) {
                        $transaksi->driver_id = $user->id;
                        $transaksi->save();

                        $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                        $firebases
                            ->withNotification('Pesanan berhasil mendapatkan driver', "Pesanan {$transaksi->id} berhasil mendapatkan driver.")
                            ->withData([
                                'title' => 'Pesanan berhasil mendapatkan driver',
                                'body' => "Pesanan {$transaksi->id} berhasil mendapatkan driver.",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToFallback($fcmUserToken);

                        return response()->json([
                            "status" => "success",
                            "message" => "Driver berhasil terassign ke pesanan prioritas",
                            "data" => $transaksi
                        ]);
                    }
                    if ($transaksi->status === 'pesanan_masuk' & $request->status === 'diantar' & $transaksi->driver_id === null) {
                        $transaksi->driver_id = $user->id;
                        $transaksi->save();

                        $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                        $firebases
                            ->withNotification('Pesanan berhasil mendapatkan driver', "Pesanan {$transaksi->id} berhasil mendapatkan driver.")
                            ->withData([
                                'title' => 'Pesanan berhasil mendapatkan driver',
                                'body' => "Pesanan {$transaksi->id} berhasil mendapatkan driver.",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToFallback($fcmUserToken);

                        return response()->json([
                            "status" => "success",
                            "message" => "Driver berhasil terassign ke pesanan prioritas",
                            "data" => $transaksi
                        ]);
                    }
                    if ($transaksi->status === 'pesanan_diproses' & $request->status === 'diantar' & $transaksi->driver_id !== null) {
                        if ($transaksi->driver_id !== $user->id) {
                            return response()->json([
                                "status" => "forbidden",
                                "message" => "Pesanan prioritas ini sudah diambil oleh driver lain",
                            ], 403);
                        } else {
                            $transaksi->status = 'diantar';
                            $transaksi->save();

                            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                            $firebases
                                ->withNotification('Pesanan telah selesai diproses', "Pesanan {$transaksi->id} telah selesai diproses. Driver akan menuju tempat pengantaran!")
                                ->withData([
                                    'title' => 'Pesanan telah selesai diproses',
                                    'body' => "Pesanan {$transaksi->id} telah selesai diproses. Driver akan menuju tempat pengantaran!",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])->sendToFallback($fcmUserToken);

                            return response()->json([
                                "status" => "success",
                                "message" => "Driver berhasil mengubah status diproses ke diantar",
                                "data" => $transaksi
                            ]);
                        }
                    }
                    if ($transaksi->status === 'siap_diantar' & $request->status === 'diantar') {
                        if ($transaksi->driver_id === null) {
                            $transaksi->driver_id = $user->id;
                            $transaksi->status = 'diantar';
                            $transaksi->save();

                            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                            $firebases
                                ->withNotification('Pesanan Telah mendapatkan driver', "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!")
                                ->withData([
                                    'title' => 'Pesanan Telah mendapatkan driver',
                                    'body' => "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])->sendToFallback($fcmUserToken);

                            return response()->json([
                                "status" => "success",
                                "message" => "Driver berhasil mendapatkan driver dan mengubah status siap diantar ke diantar",
                                "data" => $transaksi
                            ]);
                        }
                        if ($transaksi->driver_id !== null) {
                            if ($transaksi->driver_id !== $user->id) {
                                return response()->json([
                                    "status" => "forbidden",
                                    "message" => "Pesanan prioritas ini sudah diambil oleh driver lain",
                                ], 403);
                            } else {
                                $transaksi->driver_id = $user->id;
                                $transaksi->status = 'diantar';
                                $transaksi->save();

                                $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                                $firebases
                                    ->withNotification('Pesanan Telah mendapatkan driver', "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!")
                                    ->withData([
                                        'title' => 'Pesanan Telah mendapatkan driver',
                                        'body' => "Pesanan {$transaksi->id} telah mendapatkan driver. Driver akan menuju tempat pengantaran!",
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    ])->sendToFallback($fcmUserToken);

                                return response()->json([
                                    "status" => "success",
                                    "message" => "Driver berhasil mengubah status siap diantar ke diantar",
                                    "data" => $transaksi
                                ]);
                            }
                        }
                    }
                }

                // Jika pesanan biasa (non-prioritas)
                if ($transaksi->isPriority == 0) {
                    if (in_array($transaksi->status, ['refund_selesai', 'selesai'])) {
                        return response()->json([
                            "status" => "forbidden",
                            "message" => "Pesanan sudah selesai atau direfund, tidak bisa diambil lagi",
                        ], 403);
                    }

                    // Jika sudah ada driver lain
                    if ($transaksi->driver_id !== null && $transaksi->driver_id !== $user->id) {
                        return response()->json([
                            "status" => "forbidden",
                            "message" => "Pesanan ini sudah diambil oleh driver lain",
                        ], 403);
                    }

                    // Kalau driver_id masih kosong, assign ke driver ini
                    if ($transaksi->driver_id === null) {
                        $transaksi->driver_id = $user->id;
                    }

                    // Kalau sudah diantar sebelumnya
                    if ($transaksi->status === 'diantar') {
                        return response()->json([
                            "status" => "success",
                            "message" => "Pesanan sudah diambil oleh driver",
                            "data" => $transaksi
                        ]);
                    }

                    // Update status ke diantar
                    $transaksi->status = 'diantar';
                    $transaksi->driver_id = $user->id;
                    $transaksi->save();

                    $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                    $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                    if ($transaksi->status == 'diantar') {
                        $firebases
                            ->withNotification('Pesanan Sedang Diantar', "Pesanan {$transaksi->id} sedang diantar oleh driver. Mohon tunggu sebentar!")
                            ->withData([
                                'title' => 'Pesanan Sedang Diantar',
                                'body' => "Pesanan {$transaksi->id} sedang diantar oleh driver. Mohon tunggu sebentar!",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToFallback($fcmUserToken);
                    }

                    return response()->json([
                        "status" => "success",
                        "message" => "Pesanan berhasil diambil oleh driver",
                        "data" => $transaksi,
                    ]);
                }
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

                if ($transaksi->status === 'selesai' && $request->status === 'siap_diantar' && !$user->can('admin cancel order')) {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Pesanan sudah selesai"
                    ], 403);
                }

                if ($transaksi->status === 'diantar' && $request->status === 'siap_diantar' && !$user->can('admin cancel order')) {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Pesanan sudah diantar"
                    ], 403);
                }

                if ($transaksi->status === 'diantar' && $request->status === 'diantar') {
                    return response()->json([
                        "status" => "forbidden",
                        "message" => "Pesanan sudah diantar"
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

                if ($request->status === 'selesai') {
                    if (!$request->hasFile('bukti_pengantaran')) {
                        return response()->json([
                            "status" => "Bad Request",
                            "message" => "Upload bukti pengantaran"
                        ], 400);
                    }

                    $image = $request->file('bukti_pengantaran');
                    $filename = uniqid() . '.' . $image->getClientOriginalExtension();

                    $path = storage_path('app/public/bukti_pengantaran/' . $filename);

                    Image::make($image)
                        ->resize(1080, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        })
                        ->save($path, 70);

                    $transaksi->bukti_pengantaran = 'bukti_pengantaran/' . $filename;
                }

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
                    "data" => $transaksi
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
