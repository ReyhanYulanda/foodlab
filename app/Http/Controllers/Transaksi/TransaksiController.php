<?php

namespace App\Http\Controllers\Transaksi;

use App\Helper\TransaksiCek;
use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\Menus;
use App\Models\Pengaturan;
use App\Response\ResponseApi;
use App\Services\Firebases;
use App\Services\Midtrans;
use App\Traits\CanAntar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TransaksiController extends Controller
{
    public function orderUser(Request $request)
    {
        $user = $request->user();
        $permission = $user->can('read order user');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
        $transaksi = Transaksi::with(['listTransaksiDetail.menus.tenants', 'user'])
            ->whereHas('listTransaksiDetail.menus.tenants', function($tenant) use($user){
                $tenant->where('user_id', '!=', $user->id);
            })
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => [
                'transaksi' => $transaksi
            ],
        ]);
    }

    public function orderTenant(Request $request)
    {
        $user = $request->user();
        $permission = $user->can('read order tenant');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
        try {
            $tenant = Tenants::where("user_id", $request->user()->id)->first();
            $transaksi = Transaksi::whereHas('listTransaksiDetail.menus', function($menus)use($tenant){
                return $menus->where('tenant_id', $tenant->id);
            })->
            with(['listTransaksiDetail.menus.tenants' => function($tenants)use($tenant){
                $tenants->where('id', $tenant->id);
            }, 'user'])->orderByDesc('created_at')->get();

            return response()->json([
                "status" => "success",
                "message" => "Berhasil mengambil data",
                "data" => [
                    "transaksi" => array_values($transaksi->toArray())
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

    public function orderMasbro(Request $request)
    {
        $user = $request->user();
        $permission = $user->can('read order tenant');
        $permission = true; // Ini seharusnya tidak perlu jika permission dicek
    
        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
    
        try {
            // Filter hanya transaksi dengan driver_id sesuai user yang login
            $transaksi = Transaksi::where('isAntar', 1)
                ->where('driver_id', $user->id) // Hanya transaksi milik driver yang login
                ->whereIn('status', ['siap_diantar', 'diantar', 'selesai'])
                ->with(['listTransaksiDetail.menus.tenants', 'user'])
                ->orderByDesc('created_at')
                ->get();
    
            return response()->json([
                "status" => "success",
                "message" => "Berhasil mengambil data",
                "data" => [
                    "transaksi" => $transaksi
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
    
    public function store(Request $request, Firebases $firebases)
    {
        $user = $request->user();
        $permission = $user->can('create order');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validatator = Validator::make($request->all(), [
            'isAntar' => 'required|boolean',
            'ruangan_id' => 'required_if:isAntar,true',
            'metode_pembayaran' => 'required|in:koin',
            'catatan' => 'nullable',
            'status' => 'nullable',
            'menus' => 'required|array',
        ]);

        if ($validatator->fails()) {
            return response()->json([
                'status' => 'failed',
                'messages' => $validatator->errors()->all()
            ]);
        }

        DB::beginTransaction();
        try {
            $menu_id = $request->menus[0]['id'];
            $tenantUser = User::whereHas('tenant', function ($tenant) use ($menu_id) {
                $tenant->whereHas('listMenu', function ($kelola) use ($menu_id) {
                    $kelola->where('id', $menu_id);
                });
            })->first();
            
            if (!$tenantUser) {
                Log::warning('User tenant tidak ditemukan berdasarkan menu_id', ['menu_id' => $menu_id]);
            }

            $status = @$request->status ?? ($request->metode_pembayaran == 'cod' || $request->metode_pembayaran == 'koin' ? "pesanan_masuk" : "pending");

            $totalHargaMenu = 0;
            foreach ($request->menus as $menu) {
                $menuModel = Menus::withTrashed()->find($menu['id']);
                if ($menuModel) {
                    $totalHargaMenu += $menuModel->harga * $menu['jumlah'];
                }
            }
            
            $ongkosKirim = Pengaturan::where('nama', 'ongkos_kirim')->value('nilai');
            $biayaLayanan = Pengaturan::where('nama', 'biaya_layanan')->value('nilai');

            $totalFinal = $totalHargaMenu + ($request->isAntar ? $ongkosKirim : 0) + $biayaLayanan;

            $ruanganId = $request->isAntar ? $request->ruangan_id : null;
            $ongkosKirimFix = $request->isAntar ? $ongkosKirim : 0;

            $transaksi = Transaksi::create([
                'user_id' => $user->id,
                'total' => $totalFinal,
                'isAntar' => $request->isAntar,
                'metode_pembayaran' => $request->metode_pembayaran,
                'ruangan_id' => $ruanganId,
                'catatan' => @$request->catatan,
                'status' => $status,
                'ongkos_kirim' => $ongkosKirimFix,
                'biaya_layanan' => $biayaLayanan,
            ]);

            $success = $this->storeTransakasiDetail($request, $transaksi);

            if ($success) {
                DB::commit();
                if ($tenantUser && $tenantUser->fcm_token) {
                    $firebases->withNotification('Pesanan Masuk', 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!')->sendMessages($tenantUser->fcm_token);
                } 

                if($status == 'selesai'){
                    return response()->json([
                        "status" => 'success',
                        'messages' => "transaksi berhasil dibuat",
                        "order_id" => $transaksi->id,
                    ], 201);
                }

                if ($transaksi->metode_pembayaran == 'cod') {
                    return response()->json([
                        "status" => 'success',
                        'messages' => "transaksi berhasil dibuat",
                        "order_id" => $transaksi->id,
                    ], 201);
                }

                if ($transaksi->metode_pembayaran === 'koin') {
                    $saldo = SaldoKoin::where('user_id', $user->id)->first();
                
                    if (!$saldo || $saldo->jumlah < $totalFinal) {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Saldo koin tidak cukup',
                        ], 400);
                    }
                
                    $saldo->jumlah -= $totalFinal;
                    $saldo->save();
                
                    TransaksiSaldoKoin::create([
                        'user_id' => $user->id,
                        'jumlah' => -$totalFinal, 
                        'tipe' => 'keluar',
                        'deskripsi' => 'Pembayaran pesanan #' . $transaksi->id,
                    ]);
                }                

                $transaksi = Transaksi::with(['user', 'listTransaksiDetail.menus'])->where('id', $transaksi->id)->first();

                DB::commit();

                return response()->json([
                    "status" => 'success',
                    'messages' => "transaksi berhasil dibuat",
                    "order_id" => $transaksi->id,
                ], 201);
            } else {
                DB::rollback();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'gagal transaksi detail',
                ], 401);
            }

        } catch (Throwable $th) {
            DB::rollback();
            Log::error('Transaksi gagal: ' . $th->getMessage());
            Log::error('Trace: ' . $th->getTraceAsString());
        
            return response()->json([
                'status' => 'failed',
                'messages' => 'transaksi gagal: ' . $th->getMessage(),
            ], 400);
        }        
    }

    public function storeTransakasiDetail($request, $transaksi)
    {
        $validator = Validator::make($request->only(['menus']), [
            'menus' => ['required', 'array'],
            'menus.*.id' => ['required', 'numeric', 'exists:menus,id'],
            'menus.*.jumlah' => ['required', 'numeric'],
            'menus.*.catatan' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return false;
        }

        $dataInsert = [];

        foreach ($request->menus as $menu) {
            // Ambil data menu dari database berdasarkan id
            $menuModel = Menus::withTrashed()->find($menu['id']);

            if (!$menuModel) {
                // Jika menu tidak ditemukan, skip / bisa juga throw error
                continue;
            }

            $dataInsert[] = [
                'transaksi_id' => $transaksi->id,
                'menu_id' => $menu['id'],
                'jumlah' => $menu['jumlah'],
                'harga' => $menuModel->harga * $menu['jumlah'],
                'catatan' => $menu['catatan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($dataInsert)) {
            TransaksiDetail::insert($dataInsert);
            return true;
        }

        return false;
    }

    public function webHookMidtrans(Request $request, Firebases $firebases)
    {
        $midtrans = new Midtrans();
        $notif = $midtrans->notification();

        try {
            $transaction = $notif->transaction_status;
            $order_id = $notif->order_id;

            $order_id = explode('_', $order_id);
            $order_id = $order_id[1];

            $transaksi = Transaksi::find($order_id);
            $tenant = $transaksi->listTransaksiDetail->first()->menus->tenants->pemilik;

            if ($transaction == 'settlement') {
                $transaksi->update(['status' => 'pesanan_masuk']);
                $firebases->withNotification('Pesanan Masuk', "Ada Pesanan Masuk!")
                    ->sendMessages($tenant->fcm_token);
            } else if ($transaction == 'expired') {
                $transaksi->update(['status' => $transaction]);
            } else if ($transaction == 'cancel') {
                $transaksi->update(['status' => $transaction]);
            }
        } catch (Throwable $th) {
            dd($transaksi);
        }
    }

    public function refund(Transaksi $transaksi){
        try{
            $midtrans = new Midtrans();

            $refund = $midtrans->refundTransaction($transaksi);

            return ResponseApi::success(null, $refund["status_message"]);
        }catch(Exception $e){
            return response()->json([
                "status" => "failed",
                "message" => "Refund Gagal, Silahkan Coba Lagi Nanti",
            ]);
        }
    }

    public function cancel($id)
    {
        try {
            DB::beginTransaction();

            $transaksi = Transaksi::find($id);

            if (!$transaksi) {
                return ResponseApi::error("Transaksi tidak ditemukan", 404);
            }

            if (in_array($transaksi->status, ['refund_selesai', 'refund_diproses'])) {
                return ResponseApi::error("Transaksi sudah direfund sebelumnya", 400);
            }

            if ($transaksi->status === 'refund_gagal') {
                return ResponseApi::error("Refund sebelumnya gagal. Silakan hubungi admin", 400);
            }

            $transaksi->status = 'pesanan_ditolak';
            $transaksi->save();

            try {
                $transaksi->refundKoin();

                TransaksiSaldoKoin::create([
                    'user_id' => $transaksi->user_id,
                    'jumlah' => $transaksi->total, 
                    'tipe' => 'masuk',
                    'deskripsi' => 'Refund pesanan #' . $transaksi->id,
                ]);

                $transaksi->status = 'refund_selesai';
                $transaksi->save();
                DB::commit();

                return ResponseApi::success(null, "Transaksi dibatalkan dan refund berhasil");
            } catch (\Throwable $e) {
                $transaksi->status = 'refund_gagal';
                $transaksi->save();
                DB::commit();

                Log::warning("Refund gagal: " . $e->getMessage());
                return ResponseApi::error("Transaksi dibatalkan, tapi refund gagal. Silakan hubungi admin.");
            }

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi: " . $th->getMessage());
            return ResponseApi::serverError();
        }
    }
}
