<?php

namespace App\Http\Controllers\Carer;

use App\Http\Controllers\Controller;
use App\Models\Service;

class PocketController extends Controller
{
    public function __invoke()
    {
        $carer = auth()->user()->carer;

        return view('carer.pocket', [
            'total' => $carer?->pocketForMonth() ?? 0,
            'services' => Service::with('patient')
                ->where('carer_id', auth()->user()->carer_id)
                ->where('status', 'DONE')
                ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->orderByDesc('starts_at')
                ->get(),
        ]);
    }
}
