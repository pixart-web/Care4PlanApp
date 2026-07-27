<x-layouts.app title="Escalas - Care4All">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/pt.global.min.js"></script>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Escalas</h1>
        <p class="text-sm text-stone-500">A tua agenda de servicos.</p>
    </div>
    <div class="grid gap-6 xl:grid-cols-[1fr_320px]" x-data="carerScalesPage()">
        <section class="card p-5"><div id="calendar" class="min-h-[720px]"></div></section>
        <aside class="card p-5" x-show="selected">
            <h2 class="text-lg font-semibold" x-text="selected?.title"></h2>
            <p class="mt-2 text-sm text-stone-500" x-text="selected?.address"></p>
            <p class="mt-4 text-sm"><strong>Estado:</strong> <span x-text="selected?.status"></span></p>
            <div class="mt-5 flex gap-2">
                <form method="POST" :action="`/carer/scales/${selected?.id}/done`">@csrf<button class="btn-primary" type="submit">Realizado</button></form>
                <form method="POST" :action="`/carer/scales/${selected?.id}/undo`">@csrf<button class="btn-secondary" type="submit">Desfazer</button></form>
            </div>
        </aside>
    </div>
    <script>
        function carerScalesPage() {
            return {
                selected: null,
                init() {
                    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                    locale: 'pt',
                    timeZone: 'Europe/Lisbon',
                    initialView: 'timeGridWeek',
                    height: 'auto',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                    events: '{{ route('carer.scales.events') }}',
                    eventClick: ({ event }) => this.selected = { id: event.id, title: event.title, status: event.extendedProps.status, address: event.extendedProps.address }
                });
                    calendar.render();
                }
            };
        }
    </script>
</x-layouts.app>
