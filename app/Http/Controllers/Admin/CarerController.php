<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarerRequest;
use App\Http\Requests\UpdateCarerRequest;
use App\Models\Carer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CarerController extends Controller
{
    public function index()
    {
        return view('admin.carers.index', [
            'carers' => Carer::with('user')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.carers.form', ['carer' => new Carer()]);
    }

    public function store(StoreCarerRequest $request)
    {
        DB::transaction(function () use ($request) {
            $carer = Carer::create([
                ...$request->safe()->only(['name', 'phone', 'email', 'age']),
                'color_hex' => $request->input('color_hex') ?: Carer::generateUniqueColor(),
            ]);

            $user = User::create([
                'name' => $carer->name,
                'username' => $request->string('username'),
                'password' => Hash::make($request->string('password')),
                'role' => 'CARER',
                'carer_id' => $carer->id,
            ]);

            $carer->update(['user_id' => $user->id]);
        });

        return redirect()->route('admin.carers.index')->with('status', 'Cuidadora criada.');
    }

    public function edit(Carer $carer)
    {
        return view('admin.carers.form', ['carer' => $carer]);
    }

    public function update(UpdateCarerRequest $request, Carer $carer)
    {
        $carer->update([
            ...$request->safe()->only(['name', 'phone', 'email', 'age']),
            'color_hex' => $request->input('color_hex') ?: $carer->color_hex,
        ]);

        $carer->user?->update(['name' => $carer->name]);

        return redirect()->route('admin.carers.index')->with('status', 'Cuidadora atualizada.');
    }

    public function destroy(Carer $carer)
    {
        $carer->user?->delete();
        $carer->delete();

        return redirect()->route('admin.carers.index')->with('status', 'Cuidadora apagada.');
    }

    public function generateColor()
    {
        return response()->json(['color_hex' => Carer::generateUniqueColor()]);
    }
}
