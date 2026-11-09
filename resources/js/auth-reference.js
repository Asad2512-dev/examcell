function initReferenceAuth() {
  const uiText = (key, fallback, replacements = {}) => window.examcellText?.(key, fallback, replacements) || fallback;
  const reset = document.querySelector('[data-reset-password-form]');
  if (reset) {
    const password = reset.querySelector('[name=password]');
    const confirmation = reset.querySelector('[name=password_confirmation]');
    const validate = () => {
      const match = confirmation.value === password.value && password.value.length > 0;
      confirmation.setCustomValidity(confirmation.value && !match ? uiText('authentication.passwords_must_match', 'Passwords must match.') : '');
      reset.querySelector('[data-recovery-length]').dataset.valid = String(password.value.length >= 12);
      reset.querySelector('[data-recovery-match]').dataset.valid = String(match);
    };
    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
    validate();
  }
  const resend = document.querySelector('[data-recovery-resend]');
  if (resend) {
    const button = resend.querySelector('[data-resend-button]');
    const status = resend.querySelector('[data-resend-status]');
    const until = performance.now() + Number(resend.dataset.resendAfter || 0) * 1000;
    const update = () => {
      const seconds = Math.max(0, Math.ceil((until - performance.now()) / 1000));
      button.disabled = seconds > 0;
      status.textContent = seconds ? uiText('authentication.resend_wait', 'You can request another link in :Seconds seconds.', {Seconds:seconds}) : uiText('authentication.resend_now', 'You can request another link now.');
      return seconds;
    };
    if (update()) {
      const timer = setInterval(() => { if (!update()) clearInterval(timer); }, 250);
      window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
    }
  }

  document.querySelectorAll('[data-auth-password]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.authPassword);
      const shown = input.type === 'password';
      input.type = shown ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(shown));
      button.setAttribute('aria-label', shown ? uiText('authentication.hide_password', 'Hide password') : uiText('authentication.show_password', 'Show password'));
      button.querySelector('.ex-eye-off').hidden = shown;
      button.querySelector('.ex-eye-on').hidden = !shown;
    });
  });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initReferenceAuth,{once:true});else initReferenceAuth();
