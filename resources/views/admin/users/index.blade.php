<x-layouts.app title="Utilizadores - Care4All">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Utilizadores</h1>
        <a class="btn-primary" href="{{ route('admin.users.create') }}">Novo utilizador</a>
    </div>
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Nome de utilizador</th><th class="px-4 py-3">Função</th><th class="px-4 py-3">Cuidadora</th><th class="px-4 py-3">Ações</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @foreach($users as $user)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $user->name }}</td>
                    <td class="px-4 py-3">{{ $user->username }}</td>
                    <td class="px-4 py-3">{{ $user->role }}</td>
                    <td class="px-4 py-3">{{ $user->carer?->name ?: '-' }}</td>
                    <td class="px-4 py-3"><div class="flex gap-2"><a class="btn-secondary px-3 py-1" href="{{ route('admin.users.edit', $user) }}">Editar</a><form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Apagar utilizador?')">@csrf @method('DELETE')<button class="btn-danger px-3 py-1">Apagar</button></form></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>

