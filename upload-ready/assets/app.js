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
  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.openModal);
      if (!modal) return;
      modal.hidden = false;
      modal.querySelector('input, select, button')?.focus();
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.modal-backdrop');
      if (modal) modal.hidden = true;
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) modal.hidden = true;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop:not([hidden])').forEach((modal) => {
        modal.hidden = true;
      });
    }
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
});
