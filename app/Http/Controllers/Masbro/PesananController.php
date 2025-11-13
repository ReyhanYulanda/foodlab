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

        // 🧱 Cek hak akses
        if (!$user->can('read pengantaran')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        // 🧾 Validasi input
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
            // 🔍 Base Query
            $transaksiQuery = Transaksi::with(['listTransaksiDetail.menus.tenants', 'user'])
                ->where('status', '!=', 'refund_selesai');

            // ⚙️ Filter berdasarkan status
            if ($request->has('status')) {

                // === CASE: siap_diantar ===
                if ($request->status === 'siap_diantar') {
                    $transaksiQuery = $transaksiQuery->where(function ($q) use ($user) {
                        $q->where('status', 'siap_diantar')
                            ->orWhere(function ($sub) use ($user) {
                                $sub->where('isPriority', 1)
                                    ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses'])
                                    ->where(function ($sub2) use ($user) {
                                        $sub2->whereNull('driver_id')
                                            ->orWhere('driver_id', $user->id);
                                    });
                            });
                    })
                        // 🧩 Perbaikan utama:
                        // Ambil juga transaksi yang multitenant_id-nya null (non-multitenant)
                        // tapi exclude multitenant group yang sudah diantar/selesai
                        ->where(function ($q) {
                            $q->whereNull('multitenant_id') // include transaksi tunggal
                                ->orWhereNotIn('multitenant_id', function ($sub) {
                                    $sub->select('multitenant_id')
                                        ->from('transaksi')
                                        ->whereIn('status', ['diantar', 'selesai'])
                                        ->whereNotNull('multitenant_id');
                                });
                        });
                }

                // === CASE: diantar / selesai ===
                elseif (in_array($request->status, ['diantar', 'selesai'])) {
                    $transaksiQuery = $transaksiQuery->where(function ($q) use ($user, $request) {
                        $q->where(function ($inner) use ($user, $request) {
                            $inner->where('driver_id', $user->id)
                                ->where('status', $request->status);
                        })
                            ->orWhere(function ($inner) use ($user, $request) {
                                $inner->whereIn('multitenant_id', function ($sub) use ($user, $request) {
                                    $sub->select('multitenant_id')
                                        ->from('transaksi')
                                        ->where('driver_id', $user->id)
                                        ->where('status', $request->status);
                                });
                            });
                    });
                }

                // === CASE: status lainnya (fallback)
                else {
                    $transaksiQuery = $transaksiQuery->where('status', $request->status);
                }
            }

            // 🏢 Optional filter gedung
            if ($request->has('gedung')) {
                $transaksiQuery = $transaksiQuery->where('gedung', $request->gedung);
            }

            // 🚀 Jalankan query utama
            $transaksi = $transaksiQuery->get();

            // 🔁 Ambil semua multitenant_id yang muncul
            $multiIds = $transaksi->pluck('multitenant_id')->filter()->unique();

            // 🔁 Jika ada grup multitenant, ambil semua anggota grupnya
            if ($multiIds->isNotEmpty()) {
                $extraTransaksi = Transaksi::with(['listTransaksiDetail.menus.tenants', 'user'])
                    ->whereIn('multitenant_id', $multiIds)
                    ->where('status', '!=', 'refund_selesai')
                    ->get();

                $transaksi = $transaksi->merge($extraTransaksi)->unique('id')->values();
            }

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
                                $relatedTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();
                                if ($transaksi->multitenant_id) {
                                    foreach ($relatedTransaksi as $t) {
                                        $t->driver_id = $user->id;
                                        $t->save();
                                    }
                                } else {
                                    $transaksi->driver_id = $user->id;
                                    $transaksi->save();
                                }
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
                        if ($transaksi->multitenant_id) {
                            $relatedTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();
                            foreach ($relatedTransaksi as $t) {
                                $t->driver_id = $user->id;
                                $t->save();
                            }
                        } else {
                            $transaksi->driver_id = $user->id;
                            $transaksi->save();
                        }

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
                        if ($transaksi->multitenant_id) {
                            $relatedTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();
                            foreach ($relatedTransaksi as $t) {
                                $t->driver_id = $user->id;
                                $t->save();
                            }
                        } else {
                            $transaksi->driver_id = $user->id;
                            $transaksi->save();
                        }

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
                            if ($transaksi->multitenant_id) {
                                $relatedTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();
                                foreach ($relatedTransaksi as $t) {
                                    $t->driver_id = $user->id;
                                }
                            } else {
                                $transaksi->driver_id = $user->id;
                            }
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

                    if ($transaksi->multitenant_id) {
                        // Ambil ulang semua transaksi dalam grup multitenant
                        $allTransaksi = \App\Models\Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

                        // Cek apakah semuanya siap diantar atau sudah diantar
                        $allReadyToDeliver = $allTransaksi->every(fn($t) => in_array($t->status, ['siap_diantar', 'diantar']));

                        foreach ($allTransaksi as $t) {
                            // Kalau belum ada driver, assign driver yang sama
                            if ($t->driver_id === null) {
                                $t->driver_id = $user->id;
                            }

                            // Jika semua siap diantar → set semua ke diantar
                            if ($allReadyToDeliver) {
                                $t->status = 'diantar';
                            }

                            $t->save();

                            // Kirim notifikasi ke tenant
                            $tenantUser = User::with('fcmTokens')->find($t->user_id);
                            $tenantTokens = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                            if (!empty($tenantTokens)) {
                                $firebases
                                    ->withNotification('Pesanan Telah mendapatkan driver', "Driver sedang menjemput pesanan {$t->id}. Mohon tunggu sebentar!")
                                    ->withData([
                                        'title' => 'Pesanan Telah mendapatkan driver',
                                        'body' => "Pesanan {$t->id} sedang dijemput. Mohon tunggu sebentar!",
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    ])->sendToFallback($tenantTokens);
                            }
                        }
                    }

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

                /**
                 * 🔹 Cek kondisi multitenant refund
                 * Jika ada transaksi lain dalam grup multitenant yang refund_selesai,
                 * maka refund sesuai kondisi ongkir
                 */
                if ($transaksi->multitenant_id) {
                    // ambil semua pasangan (untuk keamanan jika ada >1)
                    $relatedItems = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->where('id', '!=', $transaksi->id)
                        ->get();

                    // cari transaksi yang berstatus refund_selesai di antara pasangan (prioritas)
                    $relatedRefund = $relatedItems->firstWhere('status', 'refund_selesai');

                    // jika tidak ada yang refund_selesai, coba first() seperti sebelumnya (opsional)
                    $related = $relatedRefund ?? $relatedItems->first();

                    // ===============================
                    // 🔹 1. LOGIKA REFUND MULTITENANT
                    // ===============================
                    if ($related && $related->status === 'refund_selesai') {
                        // gunakan transaksi yang refunded sebagai sumber data refund
                        $refundTx = $related;
                        $ongkirMulti = $refundTx->ruangan->gedung->ongkir_multitenant ?? 0;
                        $baseOngkir = $refundTx->ruangan->gedung->ongkir ?? 0;
                        $priorityOngkir = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;

                        if ($refundTx->isPriority) {
                            if ($refundTx->ongkos_kirim == $ongkirMulti) {
                                $refundAmount = $refundTx->total;
                            } else {
                                $refundAmount = max(($refundTx->total - ($baseOngkir + $priorityOngkir)) + $ongkirMulti, 0);
                            }
                        } else {
                            if ($refundTx->ongkos_kirim == $ongkirMulti) {
                                $refundAmount = $refundTx->total;
                            } else {
                                $refundAmount = max($refundTx->total - $ongkirMulti, 0);
                            }
                        }

                        // Lakukan refund ke user pemilik transaksi yang di-refund (refundTx->user)
                        $user = $refundTx->user;
                        $saldo = SaldoKoin::firstOrCreate(['user_id' => $user->id], ['jumlah' => 0]);
                        $saldo->jumlah += $refundAmount;
                        $saldo->save();

                        TransaksiSaldoKoin::create([
                            'user_id'   => $user->id,
                            'jumlah'    => $refundAmount,
                            'tipe'      => 'masuk',
                            'deskripsi' => "Refund pesanan {$refundTx->kode_pemesanan} karena pesanan multitenant lain dibatalkan",
                        ]);

                        Log::info("Refund multitenant: {$refundAmount} diberikan ke {$user->name} untuk transaksi #{$transaksi->id}");

                        // Kirim notifikasi FCM ke user
                        $fcmUser = User::with('fcmTokens')->find($user->id);
                        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                        if (!empty($fcmUserToken)) {
                            $firebases
                                ->withNotification('Refund Berhasil', "Saldo sebesar {$refundAmount} telah dikembalikan ke akunmu.")
                                ->withData([
                                    'title' => 'Refund Berhasil',
                                    'body' => "Saldo sebesar {$refundAmount} telah dikembalikan ke akunmu.",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])
                                ->sendToFallback($fcmUserToken);
                        }
                    }

                    // ============================================
                    // 🔹 2. LOGIKA AUTO-SELESAI UNTUK MULTITENANT
                    // ============================================
                    if ($request->status === 'selesai' && $transaksi->multitenant_id) {
                        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                            ->where('id', '!=', $transaksi->id)
                            ->first();

                        if ($related) {
                            // 🚫 Jika related masih pesanan_masuk atau pesanan_diproses → tolak
                            if (in_array($related->status, ['pesanan_masuk', 'pesanan_diproses', 'siap_diantar'])) {
                                return response()->json([
                                    'status' => 'failed',
                                    'message' => 'Masih ada pesanan multitenant lain yang belum selesai diproses.',
                                ], 400);
                            }

                            // ✅ Kalau related diantar → auto-selesai juga
                            if ($related->status === 'diantar') {
                                $related->status = 'selesai';
                                $related->driver_id = $user->id;
                                $related->bukti_pengantaran = $transaksi->bukti_pengantaran;
                                $related->save();

                                Log::info("Multitenant auto-selesai: Transaksi #{$related->id} otomatis diselesaikan karena pasangan #{$transaksi->id} sudah selesai.");

                                // Kirim notifikasi ke tenant terkait
                                $tenantUser = User::with('fcmTokens')->find($related->tenant->user_id ?? null);
                                if ($tenantUser) {
                                    $tenantTokens = $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray();
                                    if (!empty($tenantTokens)) {
                                        $firebases
                                            ->withNotification('Pesanan Selesai', "Pesanan multitenant #{$related->kode_pemesanan} telah otomatis selesai.")
                                            ->withData([
                                                'title' => 'Pesanan Selesai',
                                                'body' => "Pesanan multitenant #{$related->kode_pemesanan} telah otomatis selesai.",
                                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                            ])
                                            ->sendToFallback($tenantTokens);
                                    }
                                }
                            }

                            // ✅ Kalau related refund_selesai → lanjut normal (driver boleh selesaikan pesanan)
                            // Tidak perlu aksi tambahan karena pesanan lain sudah selesai via refund
                        }
                    }
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
                    if (
                        $transaksi->status === 'selesai' &&
                        $transaksi->cashback_amount > 0 &&
                        $transaksi->multitenant_id === null
                    ) {
                        $user = $transaksi->user;

                        // Ambil saldo koin user, kalau belum ada buat baru
                        $saldo = SaldoKoin::firstOrCreate(
                            ['user_id' => $user->id],
                            ['jumlah' => 0]
                        );

                        // Tambahkan cashback ke saldo
                        $saldo->jumlah += $transaksi->cashback_amount;
                        $saldo->save();

                        // Catat di TransaksiSaldoKoin
                        TransaksiSaldoKoin::create([
                            'user_id'   => $user->id,
                            'jumlah'    => $transaksi->cashback_amount,
                            'tipe'      => 'masuk',
                            'deskripsi' => "Cashback pesanan {$transaksi->kode_pemesanan} telah masuk",
                        ]);

                        // Logging
                        Log::info("Cashback: {$transaksi->cashback_amount} telah diterima oleh {$user->name}");

                        // Kirim notifikasi FCM
                        $fcmUser = User::with('fcmTokens')->find($user->id);
                        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                        if (!empty($fcmUserToken)) {
                            $title = 'Cashback berhasil didapatkan';
                            $body  = "Cashback sebanyak {$transaksi->cashback_amount} berhasil masuk ke akunmu.";

                            $firebases->withNotification($title, $body)
                                ->withData([
                                    'title'        => $title,
                                    'body'         => $body,
                                    'type'         => 'cashback',
                                    'transaksi_id' => $transaksi->id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])
                                ->sendToFallback($fcmUserToken);
                        }
                    }
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

                    // === FLOW DRIVER: ONGKIR MULTITENANT / NON-MULTITENANT ===
                    if ($transaksi->driver_id) {
                        $isMultiTenant = $transaksi->multitenant_id !== null;
                        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                            ->where('id', '!=', $transaksi->id)
                            ->first();

                        if ($related) {
                            $bothRefund =
                                in_array($transaksi->status, ['refund_selesai', 'refund']) &&
                                in_array($related->status, ['refund_selesai', 'refund']);

                            $transaksiCashback = null;
                            if ($transaksi->cashback_amount > 0) {
                                $transaksiCashback = $transaksi;
                            } elseif ($related->cashback_amount > 0) {
                                $transaksiCashback = $related;
                            }

                            $atLeastOneSelesai =
                                in_array($transaksi->status, ['selesai']) ||
                                in_array($related->status, ['selesai']);

                            if ($transaksiCashback && !$bothRefund && $atLeastOneSelesai) {
                                $user = $transaksiCashback->user;
                                $cashbackValue = $transaksiCashback->cashback_amount;

                                if ($cashbackValue > 0) {
                                    // 🔒 Cek apakah cashback untuk multitenant ini sudah pernah diberikan
                                    $cashbackAlreadyGiven = TransaksiSaldoKoin::where('user_id', $user->id)
                                        ->where('tipe', 'masuk')
                                        ->where('deskripsi', 'like', "%multitenant #{$transaksiCashback->multitenant_id}%")
                                        ->exists();

                                    if ($cashbackAlreadyGiven) {
                                        Log::info("⚠️ Cashback multitenant #{$transaksiCashback->multitenant_id} sudah pernah diberikan ke user {$user->id}, skip duplikat.");
                                        return;
                                    }

                                    // Tambahkan ke saldo user
                                    $saldo = SaldoKoin::firstOrCreate(
                                        ['user_id' => $user->id],
                                        ['jumlah' => 0]
                                    );

                                    $saldo->jumlah += $cashbackValue;
                                    $saldo->save();

                                    // Catat transaksi saldo
                                    TransaksiSaldoKoin::create([
                                        'user_id'   => $user->id,
                                        'jumlah'    => $cashbackValue,
                                        'tipe'      => 'masuk',
                                        'deskripsi' => "Cashback pesanan #{$transaksiCashback->kode_pemesanan} (multitenant #{$transaksiCashback->multitenant_id}) telah masuk",
                                    ]);

                                    Log::info("✅ Cashback: {$cashbackValue} diberikan ke {$user->name} dari transaksi #{$transaksiCashback->id}");

                                    // Kirim notifikasi FCM ke user
                                    $fcmUser = User::with('fcmTokens')->find($user->id);
                                    $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                                    if (!empty($fcmUserToken)) {
                                        $title = 'Cashback berhasil didapatkan';
                                        $body  = "Cashback sebesar {$cashbackValue} telah masuk ke akunmu.";

                                        $firebases->withNotification($title, $body)
                                            ->withData([
                                                'title'        => $title,
                                                'body'         => $body,
                                                'type'         => 'cashback',
                                                'transaksi_id' => $transaksiCashback->id,
                                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                            ])
                                            ->sendToFallback($fcmUserToken);
                                    }
                                }
                            } else {
                                Log::info("💤 Cashback dilewati — kondisi tidak terpenuhi (both refund / tidak ada yang selesai / tidak ada cashback).", [
                                    'transaksi_id' => $transaksi->id,
                                    'related_id'   => $related->id ?? null,
                                    'status1'      => $transaksi->status,
                                    'status2'      => $related->status,
                                    'cashback1'    => $transaksi->cashback_amount,
                                    'cashback2'    => $related->cashback_amount,
                                ]);
                            }
                        }

                        if ($transaksi->driver_id && $transaksi->multitenant_id) {
                            $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                                ->where('id', '!=', $transaksi->id)
                                ->first();

                            $ongkirMulti = $transaksi->ruangan->gedung->ongkir_multitenant ?? 0;
                            $baseOngkir = $transaksi->ruangan->gedung->ongkir ?? 0;
                            $priorityOngkir = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
                            $pajakPersen = 10;

                            // Tentukan status pasangan
                            $bothSelesai = $transaksi->status === 'selesai' && $related && $related->status === 'selesai';
                            $oneRefund   = $related && in_array($related->status, ['refund_selesai', 'refund']);
                            $bothRefund  = $transaksi->status === 'refund_selesai' && $related && $related->status === 'refund_selesai';

                            $totalOngkir = 0;

                            if ($transaksi->isPriority) {
                                if ($bothSelesai) {
                                    $totalOngkir = $baseOngkir + $priorityOngkir + $ongkirMulti;
                                } elseif ($oneRefund) {
                                    $totalOngkir = $baseOngkir + $priorityOngkir;
                                } elseif ($bothRefund) {
                                    $totalOngkir = 0;
                                }
                            } else {
                                if ($bothSelesai) {
                                    $totalOngkir = $baseOngkir + $ongkirMulti;
                                } elseif ($oneRefund) {
                                    $totalOngkir = $baseOngkir;
                                } elseif ($bothRefund) {
                                    $totalOngkir = 0;
                                }
                            }

                            // Potong pajak 10%
                            $pajak = ($pajakPersen / 100) * $totalOngkir;
                            $ongkirBersih = $totalOngkir - $pajak;

                            if ($ongkirBersih > 0) {
                                // Simpan ke histori
                                TransaksiSaldoKoin::create([
                                    'user_id' => $transaksi->driver_id,
                                    'jumlah' => $ongkirBersih,
                                    'tipe' => 'masuk',
                                    'deskripsi' => "Ongkir multitenant #{$transaksi->multitenant_id} | base: {$baseOngkir}, priority: {$priorityOngkir}, multi: {$ongkirMulti}, pajak: {$pajakPersen}%",
                                ]);

                                // Update saldo driver
                                $saldo = SaldoKoin::firstOrCreate(['user_id' => $transaksi->driver_id], ['jumlah' => 0]);
                                $saldo->jumlah += $ongkirBersih;
                                $saldo->save();

                                Log::info("Driver #{$transaksi->driver_id} menerima ongkir bersih {$ongkirBersih} (total: {$totalOngkir}, pajak: {$pajak})");
                            }
                        } else {
                            // === FLOW NON-MULTITENANT ===
                            $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
                            $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 0;

                            $ongkirAsli = $transaksi->ongkos_kirim;
                            $potongan = ($persentasePotongan / 100) * $ongkirAsli;
                            $ongkirBersih = $ongkirAsli - $potongan;

                            // Simpan ke histori
                            TransaksiSaldoKoin::create([
                                'user_id' => $transaksi->driver_id,
                                'jumlah' => $ongkirBersih,
                                'tipe' => 'masuk',
                                'deskripsi' => "Ongkir dari pesanan #{$transaksi->id}, potongan {$persentasePotongan}% dari {$ongkirAsli}, total masuk: {$ongkirBersih}",
                            ]);

                            // Update saldo driver
                            $saldo = SaldoKoin::firstOrCreate(
                                ['user_id' => $transaksi->driver_id],
                                ['jumlah' => 0]
                            );
                            $saldo->jumlah += $ongkirBersih;
                            $saldo->save();
                        }
                    }
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
