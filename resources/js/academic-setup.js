import { uiText } from './core/content.js';

// Saved records persist between steps; protect only forms with unsaved edits.
export function initAcademicSetup() {
  const forms = [...document.querySelectorAll('[data-setup-form]')];
  if (!forms.length) return;
  const dirty = new Set();
  let submitting = false;
  for (const form of forms) {
    const changed = event => { if (event.target.name) dirty.add(form); };
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', event => {
      if (event.defaultPrevented || !form.checkValidity()) return;
      // A different form may still contain unsaved work.
      if ([...dirty].some(other => other !== form) && !window.confirm(uiText('common.runtime.setup.leave_unsaved'))) {
        event.preventDefault(); return;
      }
      submitting = true;
      const button = form.querySelector('[type="submit"]');
      if (button) { button.setAttribute('aria-busy', 'true'); button.textContent = uiText('common.runtime.setup.saving'); }
    });
  }
  window.addEventListener('beforeunload', event => {
    if (dirty.size && !submitting) { event.preventDefault(); event.returnValue = ''; }
  });
  window.addEventListener('pageshow', () => { submitting = false; });
  document.querySelector('[data-setup-errors]')?.focus();
}
