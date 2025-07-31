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

        $selectedIds = explode(',', $request->selected_ids);

        // Query fcm_token
        $tokens = User::whereIn('id', $selectedIds)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        $firebases
            ->withNotification($request->judul, $request->isi)
            ->withData([
                'title' => $request->judul,
                'body' => $request->isi,
                'channel_id' => 'driver_fdlb_channel',
            ])
            ->sendMessages($tokens);

        return redirect()->route('notifikasi.index')->with('success', 'Notifikasi berhasil dikirim!');
    }
}
