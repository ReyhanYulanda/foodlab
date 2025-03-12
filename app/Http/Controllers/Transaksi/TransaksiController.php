<?php

namespace App\Http\Controllers\Transaksi;

use App\Helper\TransaksiCek;
use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
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
    public function index()
    {

    }

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
            // $baseQuery = DB::table('transaksi_detail')
            //     ->join('transaksi', 'transaksi.id', 'transaksi_detail.transaksi_id')
            //     ->join('menus_kelola', 'menus_kelola.id', 'transaksi_detail.menus_kelola_id')
            //     ->join('menus', 'menus.id', 'menus_kelola.menu_id')
            //     ->join('tenants', 'tenants.id', 'menus_kelola.tenant_id')
            //     ->where('tenant_id', @$tenant->id)
            //     // ->where('status', 'pesanan_masuk')
            //     ->select('transaksi_detail.*', 'menus.nama as namaMenu', 'tenants.nama_tenant as tenant')
            //     ->addSelect(DB::raw('transaksi_detail.jumlah * transaksi_detail.harga as subTotal'));
            // // ->get();

            // $dataPesananMasuk = (clone $baseQuery)->where('transaksi_detail.status', 'pesanan_masuk')->get();
            // $dataPesanan = (clone $baseQuery)->get();
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
    public function orderMasbro(Request $request)
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
            $transaksi = Transaksi::where('isAntar', 1)->with(['listTransaksiDetail.menus.tenants', 'user'])
                // ->where('user_id', $user->id)
                ->whereIn('status', ['siap_diantar', 'diantar', 'selesai'])
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
        $transaksiCek = new TransaksiCek($user, $request);
        $permission = $user->can('create order');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        // sewaktu waktu dibutuhkan
        // if (!$transaksiCek->antar()) {
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'tidak memiliki akses antar',
        //     ], 403);
        // }
        // ;

        // if (!$transaksiCek->metodePembayaran()) {
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'tidak memiliki akses transfer atau COD',
        //     ], 403);
        // }
        // ;

        $validatator = Validator::make($request->all(), [
            'isAntar' => 'required|boolean',
            'total' => 'required|numeric',
            'ruangan_id' => 'required_if:isAntar,true', //kurang exists in ruangan
            'metode_pembayaran' => 'required',
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

            $status = @$request->status ?? ($request->metode_pembayaran == 'cod' || $request->metode_pembayaran == 'koin' ? "pesanan_masuk" : "pending");

            $transaksi = Transaksi::create([
                'user_id' => $user->id,
                'total' => $request->total,
                'isAntar' => $request->isAntar,
                'metode_pembayaran' => $request->metode_pembayaran,
                'ruangan_id' => $request->ruangan_id,
                'ongkos_kirim' => $request->ongkos_kirim ?? 0,
                'catatan' => @$request->catatan,
                'biaya_layanan' => @$request->biaya_layanan ?? 1000,
                'status' => $status,
            ]);

            $success = $this->storeTransakasiDetail($request, $transaksi);

            if ($success) {
                DB::commit();
                // $firebases->withNotification('Pesanan Masuk', 'Ada Pesanan Masuk di Tenant Kamu')->sendMessages($tenantUser->fcm_token);
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

                $transaksi = Transaksi::with(['user', 'listTransaksiDetail.menus'])->where('id', $transaksi->id)->first();
                // $midtrans = new Midtrans();
                // $snapMidtrans = $midtrans->createSnapTransaction($transaksi);

                return response()->json([
                    "status" => 'success',
                    'messages' => "transaksi berhasil dibuat",
                    "order_id" => $transaksi->id,
                    // "snap" => $snapMidtrans
                ], 201);
            } else {
                return response()->json([
                    'statu' => 'failed',
                    'message' => 'gagal transaksi detail',
                ], 401);
            }

        } catch (Throwable $th) {
            DB::rollback();
            Log::error($th->getMessage());

            return response()->json([
                'status' => 'failed',
                'messages' => 'transaksi gagal',
            ], 400);
        }
    }

    public function storeTransakasiDetail($request, $transaksi)
    {
        $validator = Validator::make($request->only(['menus']), [
            'menus' => ['required', 'array'],
            'menus.*.id' => ['required', 'numeric'],
            'menus.*.jumlah' => ['required', 'numeric'],
            'menus.*.harga' => ['required', 'numeric'],
            'menus.*.catatan' => ['nullable'],
        ], [

        ]);

        if ($validator->fails()) {
            return response()->json([
                'messages' => $validator->errors()
            ], 400);
        }

        $dataInsert = array_map(function ($menu) use ($transaksi) {
            return [
                'transaksi_id' => $transaksi->id,
                'menu_id' => $menu['id'],
                'jumlah' => $menu['jumlah'],
                'harga' => $menu['harga'],
                'catatan' => $menu['catatan'] ?? '',
                'status' => $transaksi->status,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }, $request->menus);

        $transaksiDetail = TransaksiDetail::insert($dataInsert);

        return $transaksiDetail;
    }

    public function webHookMidtrans(Request $request, Firebases $firebases)
    {
        // $payload = $request->getContent();
        $midtrans = new Midtrans();
        $notif = $midtrans->notification();

        // $signatureKey = env('MIDTRANS_SERVER_KEY');

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
        // finally {
        //     return response()->json(['message' => 'Webhook received']);
        // }
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

    public function cancel($id){
        try{
            $midtrans = new Midtrans();

            $status = $midtrans->cancelTransaction($id);
            Log::info($status);
            if($status == 200){
                return ResponseApi::success(null, "Transaksi Berhasil DiBatalkan");
            }else{
                return ResponseApi::error("Transaksi Gagal DiBatalkan");
            }
        }catch(Throwable $th){
            return ResponseApi::serverError();
        }
    }
}
