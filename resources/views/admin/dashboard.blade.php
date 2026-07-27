<x-layouts.app title="Dashboard - Care4All">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-sm text-stone-500">Resumo operacional do apoio domiciliário.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><div class="text-sm text-stone-500">Cuidadoras</div><div class="mt-2 text-3xl font-bold">{{ $carersCount }}</div></div>
        <div class="card p-5"><div class="text-sm text-stone-500">Utentes</div><div class="mt-2 text-3xl font-bold">{{ $patientsCount }}</div></div>
        <div class="card p-5"><div class="text-sm text-stone-500">Serviços hoje</div><div class="mt-2 text-3xl font-bold">{{ $todayServices }}</div></div>
        <div class="card p-5"><div class="text-sm text-stone-500">Pocket mensal</div><div class="mt-2 text-3xl font-bold">{{ number_format($monthPocket, 2, ',', ' ') }} EUR</div></div>
    </div>
</x-layouts.app>

