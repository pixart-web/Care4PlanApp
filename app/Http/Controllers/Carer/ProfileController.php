<?php

namespace App\Http\Controllers\Carer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('carer.profile', ['carer' => auth()->user()->carer]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'age' => ['nullable', 'integer', 'min:16', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        auth()->user()->carer?->update($data);

        return back()->with('status', 'Perfil atualizado.');
    }
}
