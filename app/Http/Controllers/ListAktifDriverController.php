<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ListAktifDriverController extends Controller
{
    public function index()
    {
        $drivers = User::role('masbro')
            ->where('isOnline', 1)
            ->get();

        return view('pages.listDriver.index', [
            'header' => 'List Driver Aktif',
            'drivers' => $drivers,
        ]);
    }

    public function setOffline(User $user)
    {
        $user->update(['isOnline' => 0]);

        return redirect()->route('list-driver.index')
            ->with('success', 'Driver telah diubah menjadi offline.');
    }
}
