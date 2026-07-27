<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Carer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::with('carer')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(), 'carers' => Carer::orderBy('name')->get()]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'Utilizador criado.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'carers' => Carer::orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'Utilizador atualizado.');
    }

    public function destroy(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'Nao pode apagar o proprio utilizador.');
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Utilizador apagado.');
    }
}
