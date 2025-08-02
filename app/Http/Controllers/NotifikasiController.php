<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->search;
        $perPage = $request->per_page ?? 10;

        // base query
        $baseQuery = User::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        // pagination
        $users = $baseQuery->paginate($perPage);

        // ambil semua id user hasil filter
        $allUserIds = $baseQuery->pluck('id')->implode(',');

        return view('pages.notifikasi.kirimNotifikasi.index', [
            'users' => $users,
            'allUserIds' => $allUserIds,
        ]);
    }

    public function kirim(Request $request, Firebases $firebases)
    {
        $request->validate([
            'judul' => 'required|string',
            'isi' => 'required|string',
            'user_ids' => 'required|array',
        ]);

        // Ambil user dan relasi fcmTokens-nya
        $tokens = User::with('fcmTokens')
            ->whereIn('id', $request->user_ids)
            ->get()
            ->flatMap(function ($user) {
                return $user->fcmTokens->pluck('fcm_token');
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($tokens)) {
            $firebases
                ->withNotification($request->judul, $request->isi)
                ->withData([
                    'title' => $request->judul,
                    'body' => $request->isi,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tokens);
        }

        return redirect()->route('notifikasi.index')->with('success', 'Notifikasi berhasil dikirim!');
    }
}
