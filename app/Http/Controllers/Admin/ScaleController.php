<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Carer;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScaleController extends Controller
{
    public function index()
    {
        return view('admin.scales.index', [
            'carers' => Carer::orderBy('name')->get(),
            'patients' => Patient::orderBy('name')->get(),
        ]);
    }

    public function events(Request $request)
    {
        $query = Service::with(['carer', 'patient'])
            ->when($request->filled('carer_id'), fn ($q) => $q->where('carer_id', $request->integer('carer_id')))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')));

        if ($request->filled('start') && $request->filled('end')) {
            $query->where('starts_at', '<', Carbon::parse($request->input('end')))
                ->where('ends_at', '>', Carbon::parse($request->input('start')));
        }

        return $query->get()->map(fn (Service $service) => [
            'id' => $service->id,
            'title' => "{$service->patient->name} - {$service->carer->name}",
            'start' => $service->starts_at->toIso8601String(),
            'end' => $service->ends_at->toIso8601String(),
            'backgroundColor' => $service->status === 'CANCELLED' ? '#78716c' : $service->carer->color_hex,
            'borderColor' => $service->carer->color_hex,
            'extendedProps' => [
                'status' => $service->status,
                'patient' => $service->patient->name,
                'carer' => $service->carer->name,
                'hourly_rate' => $service->hourly_rate,
            ],
        ]);
    }

    public function store(ServiceRequest $request)
    {
        $data = $request->validated();
        $start = Carbon::parse($data['starts_at'])->startOfHour();

        try {
            DB::transaction(function () use ($data, $start) {
                for ($i = 0; $i < $data['duration_hours']; $i++) {
                    $slotStart = $start->copy()->addHours($i);

                    $overlap = Service::where('carer_id', $data['carer_id'])
                        ->where('status', '!=', 'CANCELLED')
                        ->where('starts_at', '<', $slotStart->copy()->addHour())
                        ->where('ends_at', '>', $slotStart)
                        ->exists();

                    if ($overlap) {
                        abort(422, 'A cuidadora ja tem escala nesse horario.');
                    }

                    Service::create([
                        'patient_id' => $data['patient_id'],
                        'carer_id' => $data['carer_id'],
                        'starts_at' => $slotStart,
                        'ends_at' => $slotStart->copy()->addHour(),
                        'hourly_rate' => $data['hourly_rate'],
                        'status' => 'SCHEDULED',
                    ]);
                }
            });
        } catch (QueryException) {
            return back()->withErrors(['starts_at' => 'A cuidadora ja tem escala nesse horario.'])->withInput();
        }

        return redirect()->route('admin.scales.index')->with('status', 'Escala criada.');
    }

    public function update(ServiceRequest $request, Service $service)
    {
        $data = $request->validated();
        $start = Carbon::parse($data['starts_at'])->startOfHour();

        $overlap = Service::where('carer_id', $data['carer_id'])
            ->whereKeyNot($service->id)
            ->where('status', '!=', 'CANCELLED')
            ->where('starts_at', '<', $start->copy()->addHour())
            ->where('ends_at', '>', $start)
            ->exists();

        if ($overlap) {
            return back()->withErrors(['starts_at' => 'A cuidadora ja tem escala nesse horario.'])->withInput();
        }

        $service->update([
            'patient_id' => $data['patient_id'],
            'carer_id' => $data['carer_id'],
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'hourly_rate' => $data['hourly_rate'],
        ]);

        return redirect()->route('admin.scales.index')->with('status', 'Escala atualizada.');
    }

    public function cancel(Service $service)
    {
        $service->update(['status' => 'CANCELLED']);

        return back()->with('status', 'Escala cancelada.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return back()->with('status', 'Escala apagada.');
    }
}
