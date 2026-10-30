function qs(selector) {
  return document.querySelector(selector);
}

function setupColorButton() {
  document.querySelectorAll('[data-generate-color]').forEach((button) => {
    button.addEventListener('click', () => {
      const scope = button.closest('form') || document;
      const input = scope.querySelector('[name="color_hex"]');
      const swatch = scope.querySelector('[data-color-preview]');
      if (!input) return;

      const used = [...document.querySelectorAll('[data-used-color]')].map((el) => el.dataset.usedColor);
      const goldenAngle = 137.508;
      let index = used.length;
      for (let attempt = 0; attempt < 720; attempt++) {
        const hue = ((index + attempt) * goldenAngle) % 360;
        const color = hslToHex(hue, 68, 46);
        if (!used.includes(color) || color === input.value) {
          input.value = color;
          if (swatch) {
            swatch.style.backgroundColor = color;
            swatch.textContent = '';
          }
          return;
        }
      }
    });
  });
}

function setupModals() {
  let active = null;
  let opener = null;
  let lastTrigger = null;
  let previousOverflow = '';
  const inertElements = new Map();
  const focusable = (modal) => [...modal.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
    .filter((element) => !element.disabled && element.tabIndex >= 0 && !element.closest('[hidden], [inert]') && element.getClientRects().length);
  const resolve = (modal) => typeof modal === 'string' ? document.getElementById(modal) : modal;
  const close = (target = active) => {
    const modal = resolve(target);
    if (!modal) return;
    modal.hidden = true;
    if (active !== modal) return;
    active = null;
    inertElements.forEach((wasInert, element) => { element.inert = wasInert; });
    inertElements.clear();
    document.body.style.overflow = previousOverflow;
    const restore = opener;
    opener = null;
    if (restore?.isConnected && !restore.closest('[hidden], [inert]') && restore.getClientRects().length) restore.focus();
    else document.getElementById('main-content')?.focus();
  };
  const open = (target, trigger) => {
    const modal = resolve(target);
    if (!modal || active === modal) return;
    const restore = trigger || lastTrigger || document.activeElement;
    if (active) close(active);
    active = modal;
    opener = restore;
    modal.hidden = false;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    // Dialogs live inside main; inert siblings along the ancestor path, never main itself.
    for (let branch = modal; branch.parentElement && branch !== document.body; branch = branch.parentElement) {
      [...branch.parentElement.children].forEach((sibling) => {
        if (sibling !== branch && sibling instanceof HTMLElement && !['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) {
          inertElements.set(sibling, sibling.inert);
          sibling.inert = true;
        }
      });
    }
    const dialog = modal.querySelector('[role="dialog"]') || modal;
    dialog.tabIndex = -1;
    const initial = focusable(modal).find((element) => element.matches('[autofocus], input:not([type="hidden"]), select, textarea'));
    (initial || focusable(modal)[0] || dialog).focus();
  };
  window.openCareModal = open;
  window.closeCareModal = close;
  document.addEventListener('click', (event) => {
    if (!active) lastTrigger = event.target.closest('button, a, [tabindex]') || document.activeElement;
  }, true);
  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      open(button.dataset.openModal, button);
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.modal-backdrop');
      close(modal);
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close(modal);
    });
    new MutationObserver(() => {
      if (!modal.hidden && active !== modal) open(modal);
      else if (modal.hidden && active === modal) close(modal);
    }).observe(modal, { attributes: true, attributeFilter: ['hidden'] });
    if (!modal.hidden) open(modal);
  });

  document.addEventListener('keydown', (event) => {
    if (!active) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    } else if (event.key === 'Tab') {
      const elements = focusable(active);
      const first = elements[0];
      const last = elements[elements.length - 1];
      if (!first) {
        event.preventDefault();
        (active.querySelector('[role="dialog"]') || active).focus();
      } else if (event.shiftKey && (document.activeElement === first || !elements.includes(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (document.activeElement === last || !elements.includes(document.activeElement))) {
        event.preventDefault();
        first.focus();
      }
    }
  });
  document.addEventListener('focusin', (event) => {
    if (active && !active.contains(event.target)) (focusable(active)[0] || active.querySelector('[role="dialog"]') || active).focus();
  });
}

function setupMobileNavigation() {
  const toggle = qs('[data-navigation-toggle]');
  const navigation = qs('#mobile-navigation');
  if (!toggle || !navigation) return;
  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    navigation.hidden = !open;
  };
  toggle.addEventListener('click', () => setOpen(navigation.hidden));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !navigation.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('click', (event) => {
    if (!navigation.hidden && !navigation.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
  });
  document.addEventListener('focusin', (event) => {
    if (!navigation.hidden && !navigation.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
  });
  window.matchMedia('(min-width: 981px)').addEventListener('change', (event) => { if (event.matches) setOpen(false); });
}

function setupReportReadTracking() {
  document.querySelectorAll('details[data-report-read]').forEach((details) => {
    let pending = false;
    let read = false;
    details.addEventListener('toggle', async () => {
      if (!details.open || pending || read) return;
      const id = details.dataset.reportRead;
      const csrf = details.dataset.csrf;
      if (!id || !csrf) return;
      pending = true;
      const data = new FormData();
      data.set('_csrf', csrf);
      data.set('action', 'mark_report_read');
      data.set('id', id);
      try {
        const response = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok || new URL(response.url || window.location.href).searchParams.get('page') === 'login') throw new Error('Report read request failed');
        read = true;
        const item = details.closest('.report-item');
        item?.classList.remove('unread');
        item?.querySelector('.report-head .badge-alert')?.remove();
        item?.querySelector('input[name="action"][value="mark_report_read"]')?.closest('form')?.remove();
      } catch {
        let message = details.querySelector('[data-read-error]');
        if (!message) {
          message = document.createElement('p');
          message.dataset.readError = '';
          message.className = 'alert alert-error';
          message.setAttribute('role', 'status');
          details.appendChild(message);
        }
        message.textContent = 'Não foi possível marcar como lido. Volte a abrir o relatório para tentar novamente.';
      } finally {
        pending = false;
        if (read) details.querySelector('[data-read-error]')?.remove();
      }
    });
  });
}

function setupCarerSelectors() {
  document.querySelectorAll('[data-carer-selector]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest('form');
      const selected = select.selectedOptions[0];
      if (!form || !selected || !selected.value) return;

      form.querySelector('[name="id"]').value = selected.value;
      form.querySelector('[name="name"]').value = selected.dataset.name || '';
      form.querySelector('[name="phone"]').value = selected.dataset.phone || '';
      form.querySelector('[name="email"]').value = selected.dataset.email || '';
      form.querySelector('[name="password"]').value = '';
      const pocketAdjustment = form.querySelector('[name="pocket_adjustment"]');
      if (pocketAdjustment) pocketAdjustment.value = selected.dataset.pocketAdjustment || '0';

      const color = selected.dataset.color || '';
      const colorInput = form.querySelector('[name="color_hex"]');
      const preview = form.querySelector('[data-color-preview]');
      if (colorInput) colorInput.value = color;
      if (preview) {
        preview.style.backgroundColor = color || '#ffffff';
        preview.textContent = '';
      }
    });
  });
}

function setupPatientSelectors() {
  document.querySelectorAll('[data-patient-selector]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest('form');
      const selected = select.selectedOptions[0];
      if (!form || !selected || !selected.value) return;

      form.querySelector('[name="id"]').value = selected.value;
      Object.keys(selected.dataset).forEach((key) => {
        const field = key.replace(/[A-Z]/g, (letter) => '_' + letter.toLowerCase());
        const input = form.querySelector(`[name="${field}"]`);
        if (input) input.value = selected.dataset[key] || '';
      });
    });
  });
}

function setupUserSelectors() {
  document.querySelectorAll('[data-user-selector]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest('form');
      const selected = select.selectedOptions[0];
      if (!form || !selected || !selected.value) return;

      form.querySelector('[name="id"]').value = selected.value;
      form.querySelector('[name="name"]').value = selected.dataset.name || '';
      form.querySelector('[name="username"]').value = selected.dataset.username || '';
      form.querySelector('[name="role"]').value = selected.dataset.role || 'CARER';
      form.querySelector('[name="carer_id"]').value = selected.dataset.carerId || '';
      form.querySelector('[name="password"]').value = '';
      const pocketAdjustment = form.querySelector('[name="pocket_adjustment"]');
      if (pocketAdjustment) pocketAdjustment.value = selected.dataset.pocketAdjustment || '0';
    });
  });
}

function setupReportReplies() {
  document.querySelectorAll('[data-reply-report-id]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById('reply-report-modal');
      if (!modal) return;

      const idInput = modal.querySelector('[data-reply-id]');
      const titleTarget = modal.querySelector('[data-reply-title]');
      const body = modal.querySelector('textarea[name="body"]');
      if (idInput) idInput.value = button.dataset.replyReportId || '';
      if (titleTarget) titleTarget.textContent = button.dataset.replyReportTitle || '';
      if (body) body.value = '';
    });
  });
}
function hslToHex(h, s, l) {
  s /= 100;
  l /= 100;
  const c = (1 - Math.abs(2 * l - 1)) * s;
  const x = c * (1 - Math.abs((h / 60) % 2 - 1));
  const m = l - c / 2;
  let r = 0, g = 0, b = 0;
  if (h < 60) [r, g, b] = [c, x, 0];
  else if (h < 120) [r, g, b] = [x, c, 0];
  else if (h < 180) [r, g, b] = [0, c, x];
  else if (h < 240) [r, g, b] = [0, x, c];
  else if (h < 300) [r, g, b] = [x, 0, c];
  else [r, g, b] = [c, 0, x];
  return '#' + [r, g, b].map((v) => Math.round((v + m) * 255).toString(16).padStart(2, '0')).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  setupColorButton();
  setupModals();
  setupCarerSelectors();
  setupPatientSelectors();
  setupUserSelectors();
  setupReportReplies();
  setupMobileNavigation();
  setupReportReadTracking();
});
