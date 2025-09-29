<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DriverDetail;
use App\Models\Konfigurrasi\Menu;
use App\Models\Transaksi;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Google\Client as GoogleClient;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada akses'
            ], 403);
        }

        $user = User::find($user->id);

        $menu = Menu::whereHas('device', function ($device) {
            return $device->where('device_id', 1);
        })->orderby('urutan')->get();

        $menu = $menu->filter(function ($mm) use ($user) {
            if ($user->can('read ' . $mm->nama)) {
                return $mm;
            }
        })->values();

        $permission = ($user->getPermissionsViaRoles()->pluck('name')->toArray());

        $token = $user->createToken('secret', $permission)->plainTextToken;
        $data = [
            'nama' => $user->name,
            'email' => $user->email,
            'token' => $token,
            'token_type' => 'Bearer',
            'isOnline' => $user->isOnline,
            'phone' => $user->phone,
            'gambar' => $user->image,
            'role' => $user->getRoleNames(),
            'menu' => $menu,
            'permission' => $permission,
        ];

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'pengguna tidak ditemukan'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mendapatkan data',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $valdidator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['unique:users,email'],
            'password' => 'required'
        ]);

        if ($valdidator->fails()) {
            return response()->json([
                'messages' => $valdidator->errors(),
            ]);
        }

        $newUser = User::create([
            'nama' => $request->nama,
            'email' => $request->nama,
            'password' => Hash::make($request->password),
        ]);

        return $newUser;
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json(compact('user'));
    }

    public function update(Request $request, Firebases $firebases)
    {
        $user = $request->user();
        $this->authorize('update akun');

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada akses'
            ], 403);
        }

        $user = User::find($user->id);

        $validator = Validator::make($request->all(), [
            'name' => ['string', 'nullable'],
            'email' => ['nullable', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable',
            'phone' => 'nullable',
            'isOnline' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $url = $user->image;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('public/images');
            $url = Storage::url($path);
        }

        try {
            // Siapkan data update
            $data = [
                "name" => $request->name ?? $user->name,
                "email" => $request->email ?? $user->email,
                "password" => $request->password ? Hash::make($request->password) : $user->password,
                "phone" => $request->phone ?? $user->phone,
                "image" => $request->has('delete_image') ? null : $url ?? $user->image,
            ];

            if ($request->has('delete_image')) {
                Storage::delete($user->image);
                $user->image = null;
            }

            if ($request->has('isOnline')) {
                if ($user->hasRole('masbro') && $request->isOnline == 1) {
                    $readyOrder = Transaksi::where('status', 'siap_diantar')->first();

                    if ($readyOrder) {
                        $tokens = $user->loadMissing('fcmTokens')->fcmTokens->pluck('fcm_token')->filter()->unique()->values()->toArray();

                        $firebases
                            ->withNotification(
                                'Ada Pesanan Siap Diantar',
                                "Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!"
                            )
                            ->withData([
                                'title' => 'Ada Pesanan Siap Diantar',
                                'body' => "Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])
                            ->sendToDriver($tokens);

                        Log::info("Mengirim notifikasi ke driver {$user->id} untuk pesanan siap diantar (Transaksi ID: {$readyOrder->id})");
                    }
                }
                // Cegah jika masih ada transaksi aktif
                if ($request->has('isOnline') && $request->isOnline == 0) {
                    if ($user->hasRole('tenant')) {
                        $tenant = $user->tenant;

                        $hasProcessingOrders = Transaksi::where('status', 'pesanan_diproses')
                            ->whereHas('listTransaksiDetail', function ($q) use ($tenant) {
                                $q->whereHas('menus', function ($q2) use ($tenant) {
                                    $q2->where('tenant_id', $tenant->id);
                                });
                            })->exists();

                        if ($hasProcessingOrders) {
                            return response()->json([
                                'status' => 'failed',
                                'message' => 'Tidak dapat offline karena masih ada pesanan yang sedang diproses.'
                            ], 400);
                        }
                    }

                    if ($user->hasRole('masbro')) {
                        // Cek apakah hanya ada 1 driver online
                        $jumlahDriverOnline = User::where('isOnline', true)
                            ->whereHas('roles', function ($q) {
                                $q->where('name', 'masbro');
                            })
                            ->count();

                        // Cek apakah masih ada minimal 1 transaksi 'pesanan_diproses'
                        $adaTransaksiDiproses = Transaksi::where('isAntar', 1)
                            ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses', 'siap_diantar'])
                            ->exists();

                        if ($jumlahDriverOnline === 1 && $adaTransaksiDiproses) {
                            return response()->json([
                                'status' => 'failed',
                                'message' => 'Masih ada pesanan yang sedang diproses atau siap diantar.'
                            ], 400);
                        }

                        // Tetap cek jika ada pesanan status 'diantar' oleh driver ini
                        $masihAntarPesanan = Transaksi::where('driver_id', $user->id)
                            ->where('status', 'diantar')
                            ->exists();

                        if ($masihAntarPesanan) {
                            return response()->json([
                                'status' => 'failed',
                                'message' => 'Tidak dapat offline karena masih ada pesanan yang sedang Anda antar.'
                            ], 400);
                        }
                    }
                }

                $data['isOnline'] = $request->isOnline;

                if ($user->hasRole('tenant')) {
                    $data['manual_offline'] = $request->isOnline == 0 ? true : false;
                    $data['manual_override'] = true;
                }
            }

            // ✅ Update dilakukan setelah pengecekan selesai
            $user->update($data);

            return response()->json([
                'messages' => 'Update Berhasil',
                'data' => $user
            ]);
        } catch (\Throwable $e) {
            return ResponseApi::serverError();
        }
    }



    public function updateFcmToken(Request $request, Firebases $firebases)
    {
        $user = $request->user();

        $valdidator = Validator::make($request->all(), [
            'fcm_token' => 'string'
        ]);

        if ($valdidator->fails()) {
            return response()->json($valdidator->errors()->all());
        }

        $updated = $firebases->updateFcmToken($user->id, $request->token);

        if (!$updated) {
            return response()->json(['messages' => 'Update Gagal']);
        } else {
            return response()->json([
                'messages' => 'Update Berhasil',
                'data' => $updated
            ]);
        }
    }

    public function destroy($id)
    {
        $deleted = User::findOrFail($id)->delete();
        if (!$deleted) {
            return response()->json(['messages' => 'Update Gagal']);
        } else {
            return response()->json([
                'messages' => 'Delete Berhasil',
                'data' => $deleted
            ]);
        }
    }

    public function postRekeningPencairan(Request $request)
    {
        $user = Auth::user();

        // Pastikan hanya role masbro yang boleh
        if ($user->hasRole !== 'masbro') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya driver yang bisa update rekening.'
            ], 403);
        }

        $validated = $request->validate([
            'no_rekening' => 'required|string|max:50',
        ]);

        // updateOrCreate → kalau belum ada bikin, kalau ada update
        $driverDetail = DriverDetail::updateOrCreate(
            ['user_id' => $user->id],
            ['no_rekening' => $validated['no_rekening']]
        );

        return response()->json([
            'message' => 'Rekening berhasil disimpan',
            'data' => $driverDetail
        ]);
    }

    public function getDataDriver(Request $request)
    {
        $user = $request->user();

        if (! $user->hasRole('masbro')) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya driver yang bisa mengakses data ini.'
            ], 403);
        }

        $driverDetail = $user->driverDetail;

        if (! $driverDetail) {
            return response()->json([
                'message' => 'Data driver belum tersedia.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Data driver berhasil diambil',
            'data' => $driverDetail
        ]);
    }
}
