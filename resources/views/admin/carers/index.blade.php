<x-layouts.app title="Cuidadoras - Care4All">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Cuidadoras</h1>
            <p class="text-sm text-stone-500">Equipa, estado atual e pocket do mes.</p>
        </div>
        <a class="btn-primary" href="{{ route('admin.carers.create') }}">Nova cuidadora</a>
    </div>
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-500">
            <tr>
                <th class="px-4 py-3">Nome</th><th class="px-4 py-3">Telefone</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Pocket do mês</th><th class="px-4 py-3">Cor</th><th class="px-4 py-3">Ações</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @foreach($carers as $carer)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $carer->name }}</td>
                    <td class="px-4 py-3">{{ $carer->phone }}</td>
                    <td class="px-4 py-3">{{ $carer->email ?: '-' }}</td>
                    <td class="px-4 py-3"><span class="rounded-full bg-stone-100 px-2 py-1 text-xs">{{ $carer->current_status }}</span></td>
                    <td class="px-4 py-3">{{ number_format($carer->pocketForMonth(), 2, ',', ' ') }} EUR</td>
                    <td class="px-4 py-3"><span class="inline-flex h-6 w-10 rounded" style="background: {{ $carer->color_hex }}"></span></td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <a class="btn-secondary px-3 py-1" href="{{ route('admin.carers.edit', $carer) }}">Editar</a>
                            <form method="POST" action="{{ route('admin.carers.destroy', $carer) }}" onsubmit="return confirm('Apagar cuidadora?')">
                                @csrf @method('DELETE')
                                <button class="btn-danger px-3 py-1">Apagar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>

