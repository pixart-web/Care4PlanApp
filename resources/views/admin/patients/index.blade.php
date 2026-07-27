<x-layouts.app title="Utentes - Care4All">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Utentes</h1>
        <a class="btn-primary" href="{{ route('admin.patients.create') }}">Novo utente</a>
    </div>
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Idade</th><th class="px-4 py-3">Morada</th><th class="px-4 py-3">Ações</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @foreach($patients as $patient)
                <tr><td class="px-4 py-3 font-semibold">{{ $patient->name }}</td><td class="px-4 py-3">{{ $patient->age ?: '-' }}</td><td class="px-4 py-3">{{ $patient->address }}</td><td class="px-4 py-3"><div class="flex gap-2"><a class="btn-secondary px-3 py-1" href="{{ route('admin.patients.edit', $patient) }}">Editar</a><form method="POST" action="{{ route('admin.patients.destroy', $patient) }}" onsubmit="return confirm('Apagar utente?')">@csrf @method('DELETE')<button class="btn-danger px-3 py-1">Apagar</button></form></div></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>

