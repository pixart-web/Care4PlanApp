<x-layouts.app title="Escalas - Care4All">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/pt.global.min.js"></script>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Escalas</h1>
        <p class="text-sm text-stone-500">Criar, filtrar, editar, cancelar e apagar servicos.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]" x-data="scalePage()">
        <section class="card p-5">
            <h2 class="mb-4 text-lg font-semibold" x-text="selected.id ? 'Editar escala' : 'Nova escala'"></h2>
            <form method="POST" :action="selected.id ? `/admin/scales/${selected.id}` : '{{ route('admin.scales.store') }}'" class="space-y-4">
                @csrf
                <template x-if="selected.id"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="label">Utente</label>
                    <select class="input" name="patient_id" x-model="form.patient_id" required>
                        <option value="">Selecionar</option>
                        @foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Cuidadora</label>
                    <select class="input" name="carer_id" x-model="form.carer_id" required>
                        <option value="">Selecionar</option>
                        @foreach($carers as $carer)<option value="{{ $carer->id }}">{{ $carer->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Inicio</label>
                    <input class="input" name="starts_at" type="datetime-local" x-model="form.starts_at" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Duracao</label><input class="input" name="duration_hours" type="number" min="1" max="24" x-model="form.duration_hours" required></div>
                    <div><label class="label">Valor hora</label><input class="input" name="hourly_rate" type="number" step="0.01" min="0" x-model="form.hourly_rate" required></div>
                </div>
                <button class="btn-primary w-full" type="submit">Guardar</button>
            </form>
            <div class="mt-4 flex gap-2" x-show="selected.id">
                <form method="POST" :action="`/admin/scales/${selected.id}/cancel`">@csrf<button class="btn-secondary" type="submit">Cancelar escala</button></form>
                <form method="POST" :action="`/admin/scales/${selected.id}`" onsubmit="return confirm('Apagar escala?')">@csrf<input type="hidden" name="_method" value="DELETE"><button class="btn-danger" type="submit">Apagar</button></form>
                <button class="btn-secondary" type="button" @click="reset()">Limpar</button>
            </div>
        </section>

        <section class="card p-5">
            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <select class="input" x-model="filters.carer_id" @change="calendar.refetchEvents()">
                    <option value="">Todas as cuidadoras</option>
                    @foreach($carers as $carer)<option value="{{ $carer->id }}">{{ $carer->name }}</option>@endforeach
                </select>
                <select class="input" x-model="filters.patient_id" @change="calendar.refetchEvents()">
                    <option value="">Todos os utentes</option>
                    @foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->name }}</option>@endforeach
                </select>
            </div>
            <div id="calendar" class="min-h-[720px]"></div>
        </section>
    </div>

    <script>
        function toLocalInput(value) {
            const date = new Date(value);
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 16);
        }
        function scalePage() {
            return {
                calendar: null,
                selected: {},
                form: { patient_id: '', carer_id: '', starts_at: '', duration_hours: 1, hourly_rate: '' },
                filters: { carer_id: '', patient_id: '' },
                reset() {
                    this.selected = {};
                    this.form = { patient_id: '', carer_id: '', starts_at: '', duration_hours: 1, hourly_rate: '' };
                },
                init() {
                    this.calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                        locale: 'pt',
                        timeZone: 'Europe/Lisbon',
                        initialView: 'timeGridWeek',
                        height: 'auto',
                        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                        slotMinTime: '06:00:00',
                        slotMaxTime: '23:00:00',
                        events: (info, success, failure) => {
                            const params = new URLSearchParams({ start: info.startStr, end: info.endStr, ...this.filters });
                            fetch(`{{ route('admin.scales.events') }}?${params}`).then(r => r.json()).then(success).catch(failure);
                        },
                        eventClick: ({ event }) => {
                            this.selected = { id: event.id };
                            const [patient, carer] = event.title.split(' - ');
                            this.form.starts_at = toLocalInput(event.start);
                            this.form.duration_hours = 1;
                            this.form.hourly_rate = event.extendedProps.hourly_rate;
                            this.form.patient_id = [...document.querySelectorAll('select[name=patient_id] option')].find(o => o.text === patient)?.value || '';
                            this.form.carer_id = [...document.querySelectorAll('select[name=carer_id] option')].find(o => o.text === carer)?.value || '';
                        },
                        dateClick: ({ date }) => {
                            this.reset();
                            this.form.starts_at = toLocalInput(date);
                        }
                    });
                    this.calendar.render();
                }
            };
        }
    </script>
</x-layouts.app>
