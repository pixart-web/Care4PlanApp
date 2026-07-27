<x-layouts.app title="Pocket - Care4All">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Pocket</h1>
        <p class="text-sm text-stone-500">Total calculado automaticamente para {{ now()->translatedFormat('F Y') }}.</p>
    </div>
    <div class="card mb-6 p-6">
        <div class="text-sm text-stone-500">Pocket do mes</div>
        <div class="mt-2 text-4xl font-bold text-care-green">{{ number_format($total, 2, ',', ' ') }} EUR</div>
    </div>
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-500"><tr><th class="px-4 py-3">Data</th><th class="px-4 py-3">Utente</th><th class="px-4 py-3">Valor</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @foreach($services as $service)
                <tr><td class="px-4 py-3">{{ $service->starts_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ $service->patient->name }}</td><td class="px-4 py-3">{{ number_format($service->hourly_rate, 2, ',', ' ') }} EUR</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
