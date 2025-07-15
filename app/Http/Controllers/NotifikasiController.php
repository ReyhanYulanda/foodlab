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

        $users = User::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        return view('pages.notifikasi.kirimNotifikasi.index', compact('users'));
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
            ])
            ->sendMessages($tokens);

        return redirect()->route('notifikasi.index')->with('success', 'Notifikasi berhasil dikirim!');
    }
}
