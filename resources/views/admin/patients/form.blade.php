<x-layouts.app :title="($patient->exists ? 'Editar utente' : 'Novo utente') . ' - Care4All'">
    <h1 class="mb-6 text-2xl font-bold">{{ $patient->exists ? 'Editar utente' : 'Novo utente' }}</h1>
    <form method="POST" action="{{ $patient->exists ? route('admin.patients.update', $patient) : route('admin.patients.store') }}" class="card max-w-2xl space-y-5 p-6">
        @csrf
        @if($patient->exists) @method('PUT') @endif
        <div><label class="label">Nome</label><input class="input" name="name" value="{{ old('name', $patient->name) }}" required></div>
        <div><label class="label">Idade</label><input class="input" name="age" type="number" min="0" max="120" value="{{ old('age', $patient->age) }}"></div>
        <div><label class="label">Morada</label><input class="input" name="address" value="{{ old('address', $patient->address) }}" required></div>
        <div class="flex gap-3"><button class="btn-primary">Guardar</button><a class="btn-secondary" href="{{ route('admin.patients.index') }}">Cancelar</a></div>
    </form>
</x-layouts.app>
