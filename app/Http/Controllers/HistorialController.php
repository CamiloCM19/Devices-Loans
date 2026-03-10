<?php

namespace App\Http\Controllers;

use App\Models\LogPrestamo;
use Illuminate\Http\Request;

class HistorialController extends Controller
{
    public function index()
    {
        $logs = LogPrestamo::with(['estudiante', 'camara'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('historial', compact('logs'));
    }
}
