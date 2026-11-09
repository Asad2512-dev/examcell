import { uiText } from './core/content.js';

export function initRoleAccounts() {
  for (const group of document.querySelectorAll('[data-account-password]')) {
    const password = group.querySelector('[name=password]');
    const confirmation = group.querySelector('[name=password_confirmation]');
    const reveal = group.querySelector('[data-account-reveal]');
    const feedback = group.querySelector('[data-password-feedback]');
    reveal?.addEventListener('click', () => {
      const shown = password.type === 'password';
      password.type = shown ? 'text' : 'password';
      confirmation.type = password.type;
      reveal.textContent = shown ? uiText('common.runtime.account.hide') : uiText('common.runtime.account.show');
      reveal.setAttribute('aria-pressed', String(shown));
    });
    group.querySelector('[data-generate-account-password]')?.addEventListener('click', () => {
      const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@$%';
      const random = crypto.getRandomValues(new Uint32Array(20));
      password.value = 'Ec!' + [...random].map(value => alphabet[value % alphabet.length]).join('');
      confirmation.value = password.value;
      password.dispatchEvent(new Event('input', {bubbles:true}));
      confirmation.dispatchEvent(new Event('input', {bubbles:true}));
      feedback.textContent = uiText('common.runtime.password.generated');
    });
    const validate = () => confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? uiText('common.runtime.password.must_match') : '');
    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
  }
  const issued = document.querySelector('[data-login-details]');
  if (!issued) return;
  issued.querySelector('[data-reveal-issued-password]')?.addEventListener('click', event => {
    const input = issued.querySelector('[data-login-value="Password"]');
    const shown = input.type === 'password';
    input.type = shown ? 'text' : 'password';
    event.currentTarget.textContent = shown ? uiText('common.runtime.account.hide') : uiText('common.runtime.account.show');
    event.currentTarget.setAttribute('aria-pressed', String(shown));
  });
  issued.querySelector('[data-copy-login]')?.addEventListener('click', async () => {
    const text = [...issued.querySelectorAll('[data-login-value]')].map(input => `${input.dataset.loginValue}: ${input.value}`).join('\n');
    const status = issued.querySelector('[data-copy-feedback]');
    try { await navigator.clipboard.writeText(text); status.textContent = uiText('common.runtime.account.copied'); }
    catch { status.textContent = uiText('common.runtime.account.clipboard'); }
  });
}
