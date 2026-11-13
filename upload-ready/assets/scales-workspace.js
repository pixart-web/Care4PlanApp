document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('service-form');
  if (!form) return;
  const field = (name) => form.elements.namedItem(name);
  const money = (value) => Number(value || 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
  const labels = { SCHEDULED: 'Agendado', DONE: 'Concluído', CANCELLED: 'Cancelado' };
  const rail = document.getElementById('planning-rail');
  const toggle = document.getElementById('toggle-planning-filters');
  const toggleRail = (open) => {
    rail.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    document.querySelector('.planning-body').classList.toggle('rail-open', open);
    if (open) rail.querySelector('button').focus();
  };
  toggle.addEventListener('click', () => toggleRail(rail.hidden));
  document.querySelector('[data-close-rail]').addEventListener('click', () => { toggleRail(false); toggle.focus(); });
  document.querySelector('.conflict-indicator')?.addEventListener('click', () => toggleRail(true));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !rail.hidden && !document.querySelector('.modal-backdrop:not([hidden])')) {
      toggleRail(false);
      toggle.focus();
    }
  });
  document.querySelectorAll('[form="scale-filter-form"]').forEach((input) => input.addEventListener('change', () => input.form.requestSubmit()));

  function previewContext() {
    const rate = Number(field('hourly_rate').value);
    const start = new Date(field('starts_at').value);
    const end = new Date(field('ends_at').value);
    document.getElementById('service-pocket-preview').textContent = money(window.carePocketEstimate ? window.carePocketEstimate(field('starts_at').value, field('ends_at').value, rate, field('hourly_rate_special').value) : Math.max(0, (end-start)/3600000)*rate);
    for (const [name, selector] of [['patient_id', '[data-preview-patient]'], ['carer_id', '[data-preview-carer]'], ['municipality', '[data-preview-municipality]']]) {
      form.querySelector(selector).textContent = field(name).value ? field(name).selectedOptions[0].textContent : 'Por selecionar';
    }
  }
  function clearService() {
    form.reset();
    field('id').value = '';
    const municipality = document.getElementById('map_municipality').value;
    field('municipality').value = ['all', '__unassigned'].includes(municipality) ? field('municipality').options[0].value : municipality;
    field('starts_at').value = form.dataset.date + 'T09:00';
    field('ends_at').value = form.dataset.date + 'T10:00';
    document.getElementById('service-form-title').textContent = 'Nova escala';
    document.getElementById('service-submit').textContent = 'Associar';
    document.getElementById('service-actions').hidden = true;
    previewContext();
  }
  function selectService(button) {
    clearService();
    const data = button.dataset;
    for (const [name, value] of Object.entries({ id: data.serviceId, patient_id: data.patientId, carer_id: data.carerId, starts_at: data.startsAt, ends_at: data.endsAt, hourly_rate: data.hourlyRate, observations: data.observations, municipality: data.municipality, hourly_rate_special: data.hourlyRateSpecial })) field(name).value = value ?? '';
    // Preserve legacy decimal rates that are not present in the integer selector.
    if (!field('hourly_rate').value && data.hourlyRate) {
      field('hourly_rate').add(new Option(money(data.hourlyRate) + '/h', data.hourlyRate));
      field('hourly_rate').value = data.hourlyRate;
    }
    document.querySelector('[name=future_series]').value = '0';
    document.querySelectorAll('.selected-service-id').forEach((input) => { input.value = data.serviceId; });
    document.getElementById('service-form-title').textContent = 'Editar escala';
    document.getElementById('service-submit').textContent = 'Guardar alterações';
    document.getElementById('service-actions').hidden = false;
    const date = new Date(data.startsAt);
    const duration = Math.max(0, (new Date(data.endsAt) - date) / 3600000);
    for (const [id, value] of Object.entries({ 'detail-patient': data.patientName, 'detail-carer': data.carerName, 'detail-date': date.toLocaleDateString('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' }), 'detail-time': data.startsAt.slice(11, 16) + ' – ' + data.endsAt.slice(11, 16), 'detail-municipality': data.municipality || 'Sem concelho', 'detail-rate': money(data.hourlyRate) + (data.hourlyRateSpecial !== '' && data.hourlyRateSpecial != null ? ' / ' + money(data.hourlyRateSpecial) : '') + '/h', 'detail-pocket': money(data.pocketValue ?? duration * Number(data.hourlyRate)), 'detail-observations': data.observations || 'Sem observações.' })) document.getElementById(id).textContent = value;
    const status = document.getElementById('detail-status');
    status.textContent = labels[data.status] || data.status;
    status.className = 'detail-status status-' + data.status.toLowerCase();
    document.getElementById('detail-conflict').hidden = data.hasConflict !== '1';
    form.dispatchEvent(new CustomEvent('service-selected'));
    previewContext();
  }
  const tooltip = document.getElementById('service-hover');
  let hovered = null;
  function hideTooltip() {
    tooltip.hidden = true;
    hovered?.removeAttribute('aria-describedby');
    hovered = null;
  }
  function showTooltip(button) {
    if (document.querySelector('.modal-backdrop:not([hidden])')) return;
    hideTooltip();
    hovered = button;
    const data = button.dataset;
    tooltip.replaceChildren();
    const dateTime = (value) => new Date(value).toLocaleString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    for (const [tag, value] of [['strong', data.patientName], ['p', data.carerName], ['p', dateTime(data.startsAt) + ' – ' + dateTime(data.endsAt)], ['p', (data.municipality || 'Sem concelho') + ' · ' + labels[data.status]], ['p', data.observations || 'Sem observações.'], ['small', 'Duplo clique para editar · Enter para detalhes']]) {
      const element = document.createElement(tag);
      element.textContent = value;
      tooltip.append(element);
    }
    tooltip.hidden = false;
    button.setAttribute('aria-describedby', 'service-hover');
    const rect = button.getBoundingClientRect();
    const box = tooltip.getBoundingClientRect();
    tooltip.style.left = Math.max(8, Math.min(rect.left, innerWidth - box.width - 8)) + 'px';
    tooltip.style.top = Math.max(8, rect.bottom + box.height + 12 > innerHeight ? rect.top - box.height - 8 : rect.bottom + 8) + 'px';
  }
  document.querySelectorAll('[data-service-id]').forEach((button) => {
    button.removeAttribute('title');
    button.addEventListener('mouseenter', () => showTooltip(button));
    button.addEventListener('mouseleave', (event) => { if (!tooltip.contains(event.relatedTarget)) hideTooltip(); });
    button.addEventListener('focus', () => showTooltip(button));
    button.addEventListener('blur', hideTooltip);
    button.addEventListener('dblclick', () => {
      hideTooltip();
      selectService(button);
      window.openCareModal?.('service-modal', button);
    });
    // A single mouse click keeps the target available for double-click editing.
    // Keyboard activation and touch open details with an explicit edit action.
    button.addEventListener('click', (event) => {
      if (event.detail === 0 || event.pointerType === 'touch') {
        hideTooltip();
        selectService(button);
        window.openCareModal?.('service-details', button);
      }
    });
  });
  tooltip.addEventListener('mouseleave', hideTooltip);
  document.addEventListener('scroll', hideTooltip, true);
  window.addEventListener('resize', hideTooltip);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideTooltip(); });
  document.querySelectorAll('[data-select-service]').forEach((button) => button.addEventListener('click', () => {
    const original = document.querySelector(`[data-service-id="${button.dataset.selectService}"]`);
    if (original) selectService(original);
  }));
  document.getElementById('edit-selected-service').addEventListener('click', () => {
    window.closeCareModal?.('service-details');
    window.openCareModal?.('service-modal', document.getElementById('edit-selected-service'));
  });
  document.getElementById('open-new-service').addEventListener('click', clearService);
  document.getElementById('clear-service').addEventListener('click', clearService);
  form.addEventListener('input', previewContext);
  form.addEventListener('change', previewContext);
  clearService();
});
