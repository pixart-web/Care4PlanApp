document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const form = document.getElementById('service-form');
  if (!form) return;
  const field = (name) => form.elements.namedItem(name);
  const submit = document.getElementById('service-submit');
  const key = document.createElement('input');
  key.type = 'hidden';
  key.name = 'idempotency_key';
  form.append(key);
  const token = document.createElement('input');
  token.type = 'hidden';
  token.name = 'preview_token';
  form.append(token);
  const newKey = () => {
    const bytes = new Uint8Array(24);
    crypto.getRandomValues(bytes);
    key.value = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  };

  const recurrence = document.createElement('fieldset');
  recurrence.id = 'recurrence-fields';
  recurrence.innerHTML = `
    <legend>Recorr\u00eancia</legend>
    <label><input type="checkbox" id="repeat-service"> Repetir esta escala noutros dias</label>
    <div id="repeat-service-options" hidden>
    <label class="field"><span class="label">Repetir</span>
      <select class="input" name="recurrence_mode" id="recurrence-mode">
        <option value="none">N\u00e3o repetir</option><option value="week_only">Esta semana</option><option value="dates">Datas escolhidas</option>
        <option value="weekdays">Intervalo de semanas</option>
      </select>
    </label>
    <div id="recurrence-dates-panel" hidden>
      <div class="form-grid"><label class="field"><span class="label">Data adicional</span><input class="input" type="date" id="recurrence-add-date"></label>
      <button class="btn btn-secondary" type="button" id="recurrence-add">Adicionar data</button></div>
      <label class="field"><span class="label">Datas (AAAA-MM-DD)</span><textarea class="input" rows="3" name="recurrence_dates" id="recurrence-dates"></textarea></label>
    </div>
    <div id="recurrence-weekdays-panel" hidden>
      <div class="btn-row"><button type="button" class="btn btn-secondary" data-weekdays="1,2,3,4,5">Dias \u00fateis</button><button type="button" class="btn btn-secondary" data-weekdays="1,2,3,4,5,6,7">Todos</button><button type="button" class="btn btn-secondary" data-weekdays="">Limpar</button></div>
      <label class="field"><span class="label">At\u00e9 (opcional)</span><input class="input" type="date" name="recurrence_until"></label>
      <div class="btn-row">${['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'S\u00e1b', 'Dom'].map((day, index) => `<label><input type="checkbox" name="recurrence_weekdays[]" value="${index + 1}"> ${day}</label>`).join('')}</div>
    </div>
    </div>
    <label><input type="checkbox" name="skip_conflicts" value="1"> Criar apenas ocorr\u00eancias sem conflitos</label>
    <button class="btn btn-secondary" type="button" id="preview-service">Pr\u00e9-visualizar</button>
    <div id="recurrence-preview" role="status" aria-live="polite"></div>`;
  const recurrenceSlot = form.querySelector('[data-recurrence-slot]');
  if (recurrenceSlot) recurrenceSlot.append(recurrence);
  else form.querySelector('.modal-actions').before(recurrence);
  const mode = field('recurrence_mode');
  const repeat = document.getElementById('repeat-service');
  const preview = document.getElementById('recurrence-preview');
  const previewButton = document.getElementById('preview-service');
  form.querySelector('[data-preview-slot]')?.append(preview);
  form.querySelector('[data-preview-actions]')?.append(previewButton);
  let generation = 0;
  let approved = '';
  let submitting = false;
  const snapshot = () => new URLSearchParams(new FormData(form)).toString();
  const formatDate = (value) => new Date(value.replace(' ', 'T')).toLocaleDateString('pt-PT', { weekday: 'short', day: '2-digit', month: '2-digit' });

  function updateMode() {
    const editing = Boolean(field('id').value);
    if (editing) mode.value = 'none';
    repeat.disabled = editing;
    if (editing) repeat.checked = false;
    document.getElementById('repeat-service-options').hidden = !repeat.checked;
    mode.disabled = editing;
    for (const [panelId, selected] of [['recurrence-dates-panel', 'dates'], ['recurrence-weekdays-panel', 'weekdays']]) {
      const panel = document.getElementById(panelId);
      panel.hidden = !(mode.value === selected || (selected === 'weekdays' && mode.value === 'week_only')) || editing;
      panel.querySelectorAll('input, textarea, button').forEach((input) => { input.disabled = panel.hidden; });
    }
    field('recurrence_until').disabled = mode.value !== 'weekdays' || editing;
    field('recurrence_until').closest('label').hidden = mode.value === 'week_only';
    submit.disabled = mode.value !== 'none' && approved !== snapshot();
  }

  function invalidate() {
    generation += 1;
    approved = '';
    token.value = '';
    newKey();
    preview.replaceChildren();
    updateMode();
  }

  function resetRecurrence() {
    mode.disabled = false;
    mode.value = 'none';
    repeat.checked = false;
    invalidate();
    const municipality = field('municipality');
    if (!municipality.value) municipality.selectedIndex = 0;
    document.querySelectorAll('.selected-service-id').forEach((input) => { input.value = field('id').value; });
  }

  form.addEventListener('input', invalidate);
  form.addEventListener('change', invalidate);
  repeat.addEventListener('change', () => {
    mode.value = repeat.checked ? 'week_only' : 'none';
    if (repeat.checked && !recurrence.querySelector('[name="recurrence_weekdays[]"]:checked')) {
      const date = new Date(`${field('starts_at').value.slice(0, 10)}T12:00:00`);
      const weekday = date.getDay() || 7;
      const input = recurrence.querySelector(`[name="recurrence_weekdays[]"][value="${weekday}"]`);
      if (input) input.checked = true;
    }
    invalidate();
  });
  mode.addEventListener('change', () => {
    repeat.checked = mode.value !== 'none';
    invalidate();
  });
  recurrence.querySelectorAll('[data-weekdays]').forEach((button) => button.addEventListener('click', () => {
    const selected = button.dataset.weekdays.split(',');
    recurrence.querySelectorAll('[name="recurrence_weekdays[]"]').forEach((input) => { input.checked = selected.includes(input.value); });
    invalidate();
  }));
  document.getElementById('recurrence-add').addEventListener('click', () => {
    const input = document.getElementById('recurrence-add-date');
    if (!input.value || !input.checkValidity()) return;
    const dates = document.getElementById('recurrence-dates');
    dates.value = [...new Set([...dates.value.split(/[\s,;]+/).filter(Boolean), input.value])].sort().join('\n');
    input.value = '';
    invalidate();
  });

  previewButton.addEventListener('click', async () => {
    if (!form.reportValidity()) return;
    const version = generation;
    const before = snapshot();
    const body = new FormData(form);
    body.set('action', 'preview_service');
    previewButton.disabled = true;
    preview.textContent = 'A verificar...';
    try {
      const response = await fetch(form.action || location.href, {
        method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' }
      });
      const type = response.headers.get('content-type') || '';
      if (!type.includes('application/json')) throw new Error('Resposta inesperada. Atualize a p\u00e1gina e tente novamente.');
      const result = await response.json();
      if (version !== generation || before !== snapshot()) return;
      if (!response.ok && !Array.isArray(result.errors)) throw new Error(result.message || 'N\u00e3o foi poss\u00edvel verificar as escalas.');
      preview.replaceChildren();
      const summary = document.createElement('p');
      summary.className = result.valid ? 'preview-result preview-result-ok' : 'preview-result preview-result-conflict';
      const count = result.count || 0;
      const eligible = result.eligible_count ?? count;
      const skipped = result.skipped_count || 0;
      summary.textContent = `${count} ${count === 1 ? 'ocorrência' : 'ocorrências'}. ${eligible} ${eligible === 1 ? 'disponível' : 'disponíveis'}${skipped ? `, ${skipped} ${skipped === 1 ? 'ignorada' : 'ignoradas'}` : ''}. ${result.valid ? 'Pode guardar.' : 'Reveja os conflitos.'}`;
      preview.append(summary);
      for (const error of result.errors || []) {
        const line = document.createElement('p');
        line.textContent = String(error);
        preview.append(line);
      }
      const conflicts = new Map((result.conflicts || []).map((conflict) => [conflict.occurrence, conflict]));
      const list = document.createElement('ol');
      for (const [index, occurrence] of (result.occurrences || []).entries()) {
        const item = document.createElement('li');
        item.textContent = `${formatDate(occurrence.starts_at)} · ${occurrence.starts_at.slice(11, 16)} – ${occurrence.ends_at.slice(11, 16)}${conflicts.has(index) ? ' / Conflito' : ''}`;
        if (conflicts.has(index)) {
          const conflict = conflicts.get(index);
          const detail = document.createElement('small');
          detail.textContent = conflict.existing_starts_at ? `Serviço existente: ${conflict.existing_starts_at.slice(11,16)} – ${conflict.existing_ends_at.slice(11,16)}` : 'Sobreposição neste lote';
          item.append(detail);
        }
        list.append(item);
      }
      preview.append(list);
      token.value = result.preview_token || '';
      approved = result.valid === true && (mode.value === 'none' || token.value) ? snapshot() : '';
      updateMode();
    } catch (error) {
      if (version === generation) preview.textContent = error.message;
    } finally {
      previewButton.disabled = false;
    }
  });

  const duplicate = document.createElement('button');
  duplicate.className = 'btn btn-secondary';
  duplicate.type = 'button';
  duplicate.textContent = 'Duplicar';
  document.querySelector('#service-actions .btn-row').prepend(duplicate);
  duplicate.addEventListener('click', () => {
    field('id').value = '';
    document.getElementById('service-form-title').textContent = 'Duplicar escala';
    submit.textContent = 'Associar';
    document.getElementById('service-actions').hidden = true;
    resetRecurrence();
    field('starts_at').focus();
  });
  document.querySelectorAll('[data-service-id]').forEach((button) => button.addEventListener('click', resetRecurrence));
  form.addEventListener('service-selected', resetRecurrence);
  document.getElementById('open-new-service').addEventListener('click', resetRecurrence);
  document.getElementById('clear-service').addEventListener('click', resetRecurrence);
  form.addEventListener('submit', (event) => {
    if (submitting || (mode.value !== 'none' && approved !== snapshot())) {
      event.preventDefault();
      if (!submitting) preview.textContent = 'Pr\u00e9-visualize as ocorr\u00eancias antes de guardar.';
      return;
    }
    submitting = true;
    submit.disabled = true;
  });
  const mobile = matchMedia('(max-width: 700px)');
  const syncViewport = () => {
    const desktop = document.getElementById('scales-desktop-calendar');
    const list = mobile.matches || desktop.dataset.period === 'list';
    document.getElementById('scales-mobile-list').hidden = !list;
    desktop.hidden = list;
  };
  mobile.addEventListener('change', syncViewport);
  syncViewport();
  const operationalScroll = document.querySelector('.scales-v2-day-scroll');
  const firstEvent = document.querySelector('.scales-v2-event');
  if (operationalScroll && firstEvent) {
    const firstMinute = Math.min(...[...document.querySelectorAll('.scales-v2-event')].map((event) => parseFloat(event.style.top)));
    operationalScroll.scrollTop = Math.max(0, firstMinute - 90);
  }
  resetRecurrence();
});
