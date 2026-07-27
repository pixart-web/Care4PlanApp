<?php

namespace App\Http\Controllers;

use App\Models\Carer;
use App\Models\Patient;
use App\Models\Service;

class DashboardController extends Controller
{
    public function redirect()
    {
        return auth()->user()->isCarer()
            ? redirect()->route('carer.scales.index')
            : redirect()->route('admin.dashboard');
    }

    public function admin()
    {
        return view('admin.dashboard', [
            'carersCount' => Carer::count(),
            'patientsCount' => Patient::count(),
            'todayServices' => Service::whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'monthPocket' => Service::where('status', 'DONE')
                ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('hourly_rate'),
        ]);
    }
}
