(() => {
  'use strict';
  const root = document.querySelector('[data-carer-agenda]');
  if (!root) return;
  const list = document.getElementById('carer-service-list');
  const panel = document.getElementById('carer-calendar-panel');
  const error = document.getElementById('carer-calendar-error');
  const buttons = [...root.querySelectorAll('[data-agenda-view]')];
  const labels = { SCHEDULED: 'Agendado', DONE: 'Conclu\u00eddo', CANCELLED: 'Cancelado' };
  let calendar;
  const showDetails = (info) => {
    const event = info.event;
    const props = event.extendedProps;
    const put = (id, value) => { document.getElementById(id).textContent = value; };
    put('selected-title', event.title);
    put('selected-address', props.address || 'Morada n\u00e3o indicada');
    put('selected-municipality', props.municipality || 'Sem concelho');
    put('selected-status', labels[props.status] || props.status || '');
    put('selected-pocket', Number(props.pocket_value || 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' }));
    put('selected-observations', props.observations || 'Sem observa\u00e7\u00f5es.');
    // Use the server's local timestamps, without browser timezone conversion.
    const stamp = (value) => {
      const parts = String(value || '').split(' ');
      return parts.length === 2 ? parts[0].split('-').reverse().join('/') + ' ' + parts[1].slice(0, 5) : '';
    };
    put('selected-time', stamp(props.starts_at) + ' - ' + stamp(props.ends_at));
    document.getElementById('done_id').value = event.id;
    document.getElementById('undo_id').value = event.id;
    document.getElementById('carer-done-form').hidden = props.status !== 'SCHEDULED';
    document.getElementById('carer-undo-form').hidden = props.status !== 'DONE';
    window.openCareModal('carer-selected', info.el);
  };
  const createCalendar = () => {
    calendar = new FullCalendar.Calendar(document.getElementById('carer-calendar'), {
      locale: 'pt', timeZone: 'Europe/Lisbon', initialDate: root.dataset.date,
      initialView: window.matchMedia('(max-width: 699px)').matches ? 'timeGridDay' : 'timeGridWeek',
      height: 'auto', firstDay: 1, nowIndicator: true,
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridDay,timeGridWeek,dayGridMonth' },
      buttonText: { today: 'Hoje', month: 'M\u00eas', week: 'Semana', day: 'Dia' },
      allDayText: 'Dia inteiro', noEventsText: 'Sem servi\u00e7os neste per\u00edodo',
      slotMinTime: '00:00:00', slotMaxTime: '24:00:00',
      slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      events(info, success, failure) {
        error.hidden = true;
        const params = new URLSearchParams({ page: 'api.events', start: info.startStr, end: info.endStr });
        fetch('index.php?' + params, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then((response) => { if (!response.ok) throw new Error('Calendar request failed'); return response.json(); })
          .then((events) => { if (!Array.isArray(events)) throw new Error('Invalid events'); success(events); })
          .catch((cause) => { error.hidden = false; failure(cause); });
      },
      eventContent(arg) {
        const container = document.createElement('div');
        container.className = 'fc-event-title-custom';
        container.textContent = [arg.timeText, arg.event.title, labels[arg.event.extendedProps.status]].filter(Boolean).join(' \u00b7 ');
        if (arg.event.extendedProps.has_observations) {
          const flag = document.createElement('span');
          flag.className = 'obs-flag'; flag.textContent = 'Obs.'; container.appendChild(flag);
        }
        return { domNodes: [container] };
      },
      eventDidMount(info) {
        info.el.setAttribute('role', 'button');
        info.el.tabIndex = 0;
        info.el.setAttribute('aria-label', [info.event.title, info.timeText, labels[info.event.extendedProps.status], 'Detalhes'].filter(Boolean).join(', '));
        info.el.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); showDetails(info); }
        });
      },
      eventClick: showDetails
    });
    calendar.render();
  };
  const setView = (view, updateUrl = false) => {
    const mode = view === 'calendar' && window.FullCalendar ? 'calendar' : 'list';
    list.hidden = mode !== 'list'; panel.hidden = mode !== 'calendar';
    buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.agendaView === mode)));
    root.querySelector('[data-agenda-view-input]').value = mode;
    root.querySelectorAll('.agenda-day-link').forEach((link) => { const url = new URL(link.href); url.searchParams.set('view', mode); link.href = url.href; });
    if (mode === 'calendar') { if (!calendar) createCalendar(); else calendar.updateSize(); }
    if (updateUrl) { const url = new URL(window.location.href); url.searchParams.set('view', mode); window.history.replaceState(null, '', url); }
  };
  if (window.FullCalendar) buttons.forEach((button) => { button.hidden = false; });
  buttons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.agendaView, true)));
  document.getElementById('carer-calendar-retry').addEventListener('click', () => calendar?.refetchEvents());
  setView(root.dataset.view || (window.matchMedia('(max-width: 699px)').matches ? 'list' : 'calendar'));
})();
