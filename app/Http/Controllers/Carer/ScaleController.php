<?php

namespace App\Http\Controllers\Carer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScaleController extends Controller
{
    public function index()
    {
        return view('carer.scales');
    }

    public function events(Request $request)
    {
        $carerId = $request->user()->carer_id;

        $query = Service::with(['patient', 'carer'])
            ->where('carer_id', $carerId);

        if ($request->filled('start') && $request->filled('end')) {
            $query->where('starts_at', '<', Carbon::parse($request->input('end')))
                ->where('ends_at', '>', Carbon::parse($request->input('start')));
        }

        return $query->get()->map(fn (Service $service) => [
            'id' => $service->id,
            'title' => $service->patient->name,
            'start' => $service->starts_at->toIso8601String(),
            'end' => $service->ends_at->toIso8601String(),
            'backgroundColor' => $service->status === 'DONE' ? '#2f6f4f' : ($service->status === 'CANCELLED' ? '#78716c' : $service->carer->color_hex),
            'borderColor' => $service->carer->color_hex,
            'extendedProps' => [
                'status' => $service->status,
                'address' => $service->patient->address,
                'hourly_rate' => $service->hourly_rate,
            ],
        ]);
    }

    public function done(Request $request, Service $service)
    {
        abort_unless($service->carer_id === $request->user()->carer_id, 403);

        $service->update([
            'status' => 'DONE',
            'done_at' => now(),
            'done_by_user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Servico marcado como realizado.');
    }

    public function undo(Request $request, Service $service)
    {
        abort_unless($service->carer_id === $request->user()->carer_id, 403);

        $service->update([
            'status' => 'SCHEDULED',
            'done_at' => null,
            'done_by_user_id' => null,
        ]);

        return back()->with('status', 'Servico reposto.');
    }
}
