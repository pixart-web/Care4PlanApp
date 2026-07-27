<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Models\Patient;

class PatientController extends Controller
{
    public function index()
    {
        return view('admin.patients.index', [
            'patients' => Patient::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.patients.form', ['patient' => new Patient()]);
    }

    public function store(PatientRequest $request)
    {
        Patient::create($request->validated());

        return redirect()->route('admin.patients.index')->with('status', 'Utente criado.');
    }

    public function edit(Patient $patient)
    {
        return view('admin.patients.form', compact('patient'));
    }

    public function update(PatientRequest $request, Patient $patient)
    {
        $patient->update($request->validated());

        return redirect()->route('admin.patients.index')->with('status', 'Utente atualizado.');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();

        return redirect()->route('admin.patients.index')->with('status', 'Utente apagado.');
    }
}
