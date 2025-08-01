<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ListAktifDriverController extends Controller
{
    public function index()
    {
        $drivers = User::role('masbro')->where('isOnline', 1)->get();
        $jumlahDriver = $drivers->count();

        return view('pages.listDriver.index', compact('drivers', 'jumlahDriver'));
    }

    public function setOffline($id)
    {
        $driver = User::findOrFail($id);

        // Optional: validasi kalau bukan 'masbro'
        if (!$driver->hasRole('masbro')) {
            return redirect()->back()->with('error', 'Bukan role masbro');
        }

        $driver->isOnline = 0;
        $driver->save();

        return redirect()->back()->with('success', 'Driver dimatikan dari status online');
    }
}
