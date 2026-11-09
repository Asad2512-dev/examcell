document.addEventListener('DOMContentLoaded', function () {
  const qs = (s, r = document) => r.querySelector(s);
  const all = (s, r = document) => [...r.querySelectorAll(s)];
  const uiText = (key, fallback = key, replacements = {}) => window.examcellText?.(key, fallback, replacements) || fallback;
  let lastTrigger = null;
  let pendingForm = null;
  let pendingSubmitter = null;
  let busy = false;

  all('[data-route-form]').forEach((form) => {
    const update = () => {
      const id = form.querySelector('[data-route-id]')?.value;
      form.action = form.dataset.routeForm + (id || '');
      form.querySelector('button[type="submit"],button:not([type])')?.toggleAttribute('disabled', !id);
    };
    form.querySelector('[data-route-id]')?.addEventListener('change', update);
    update();
  });
  all('[data-seating-schedule]').forEach(select => {
    const update = () => {
      const locked = !select.value || select.selectedOptions[0]?.dataset.locked === 'true';
      const form = select.closest('form');
      form.querySelector('[data-seating-save]').disabled = locked;
      form.querySelector('[data-seating-status]').textContent = !select.value
        ? uiText('common.runtime.seating.needs_schedule')
        : locked ? uiText('common.runtime.seating.published')
        : uiText('common.runtime.seating.preview');
    };
    select.addEventListener('change', update);
    update();
  });
  let navTrigger = null;
  const mobileNav = window.matchMedia('(max-width:768px)');
  const syncNavigation = () => {
    const open = mobileNav.matches && document.body.classList.contains('nav-open');
    if (qs('.sidebar')) qs('.sidebar').inert = mobileNav.matches && !open;
    if (qs('.app-main')) qs('.app-main').inert = open;
  };
  syncNavigation();
  mobileNav.addEventListener('change', () => {
    document.body.classList.remove('nav-open');
    all('[data-open-nav]').forEach(b => b.setAttribute('aria-expanded', 'false'));
    syncNavigation();
  });
  const closeNav = () => {
    document.body.classList.remove('nav-open');
    syncNavigation();
    navTrigger?.setAttribute('aria-expanded', 'false');
    navTrigger?.focus();
  };
  all('[data-open-nav]').forEach((b) => {
    b.setAttribute('aria-controls', 'sidebar');
    b.setAttribute('aria-expanded', 'false');
    b.addEventListener('click', () => {
      navTrigger = b;
      document.body.classList.add('nav-open');
      b.setAttribute('aria-expanded', 'true');
      syncNavigation();
      qs('.sidebar summary, .sidebar .nav-item[href]')?.focus();
    });
  });
  all('[data-close-nav]').forEach((b) => b.addEventListener('click', closeNav));
  all('[data-collapse-nav]').forEach((b) => b.addEventListener('click', () => {
    if (innerWidth > 760 && innerWidth <= 1050) document.body.classList.toggle('nav-expanded');
    else document.body.classList.toggle('nav-collapsed');
  }));
  all('.nav-item[href]').forEach((link) => link.addEventListener('click', closeNav));
  document.addEventListener('click', event => {
    if (!event.target.closest('.profile-wrap')) {
      qs('.profile-menu')?.setAttribute('hidden', '');
      qs('[data-toggle-profile]')?.setAttribute('aria-expanded', 'false');
    }
  });
  qs('#main-content')?.classList.add('page-enter');
  all('[data-toggle-profile]').forEach((b) => b.addEventListener('click', () => {
    const menu = qs('.profile-menu');
    if (!menu) return;
    menu.hidden = !menu.hidden;
    b.setAttribute('aria-expanded', String(!menu.hidden));
  }));
  qs('[data-toggle-profile]')?.addEventListener('keydown', event => {
    if (event.key !== 'ArrowDown') return;
    event.preventDefault();
    const menu = qs('.profile-menu');
    if (!menu) return;
    menu.hidden = false;
    event.currentTarget.setAttribute('aria-expanded', 'true');
    menu.querySelector('a,button')?.focus();
  });
  qs('.profile-wrap')?.addEventListener('focusout', event => {
    if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) {
      qs('.profile-menu')?.setAttribute('hidden', '');
      qs('[data-toggle-profile]')?.setAttribute('aria-expanded', 'false');
    }
  });

  const close = (dialog) => {
    if (!dialog?.open) return;
    dialog.close();
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    lastTrigger = null;
    pendingForm = null;
  };
  all('[data-open-catalog]').forEach((b) => b.addEventListener('click', () => {
    lastTrigger = b; qs('#catalog-dialog')?.showModal(); qs('#catalog-search')?.focus();
  }));
  all('[data-open-notifications]').forEach((b) => b.addEventListener('click', () => {
    lastTrigger = b; qs('#notification-dialog')?.showModal();
  }));
  all('[data-close-dialog]').forEach((b) => b.addEventListener('click', () => close(b.closest('dialog'))));

  const dialog = qs('#confirm-dialog');
  const confirmForm = qs('#confirm-form');
  const reason = qs('#confirm-reason');
  const reasonWrap = qs('#confirm-reason-wrap');
  const submit = qs('#confirm-submit');
  const openConfirm = (form, trigger, submitter) => {
    if (!dialog || !confirmForm) return;
    pendingForm = form; lastTrigger = trigger;
    if (form.matches('form[action$="/logout"]')) {
      qs('.profile-menu')?.setAttribute('hidden', '');
      qs('[data-toggle-profile]')?.setAttribute('aria-expanded', 'false');
      lastTrigger = qs('[data-toggle-profile]');
    }
    const config = {...form.dataset, ...submitter?.dataset};
    const variant = config.confirmVariant || 'standard';
    qs('#confirm-title').textContent = config.confirmTitle || uiText('common.runtime.confirmation.title');
    qs('#confirm-description').textContent = config.confirmMessage || uiText('common.runtime.confirmation.description');
    submit.textContent = config.confirmLabel || uiText('common.runtime.confirmation.label');
    submit.className = `btn ${['destructive', 'irreversible'].includes(variant) ? 'btn-destructive' : 'btn-primary'}`;
    const needsReason = config.confirmReason === 'true' || ['approval', 'rejection', 'publish', 'finalize'].includes(variant);
    reasonWrap.hidden = !needsReason;
    reason.required = needsReason;
    reason.value = '';
    dialog.showModal();
    (needsReason ? reason : submit).focus();
  };
  all('form[action$="/logout"]').forEach((form) => {
    form.dataset.confirm = 'true';
    form.dataset.confirmTitle = uiText('common.runtime.confirmation.logout_title');
    form.dataset.confirmMessage = uiText('common.runtime.confirmation.logout_message');
    form.dataset.confirmLabel = uiText('common.runtime.confirmation.logout_label');
    form.dataset.confirmVariant = 'warning';
  });
  all('form[action]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if ((event.submitter?.dataset.confirm ?? form.dataset.confirm) === 'true' && !form.dataset.confirmed) {
        event.preventDefault();
        pendingSubmitter = event.submitter;
        openConfirm(form, document.activeElement, event.submitter);
      } else if (form.dataset.confirmed) {
        delete form.dataset.confirmed;
      }
    });
  });
  confirmForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (busy || !pendingForm) return;
    busy = true; submit.disabled = true; submit.textContent = uiText('common.runtime.confirmation.processing');
    if (reason.value && !pendingForm.querySelector('[name="reason"]')) {
      const input = document.createElement('input'); input.type = 'hidden'; input.name = 'reason'; input.value = reason.value; pendingForm.appendChild(input);
    }
    pendingForm.dataset.confirmed = 'true';
    const form = pendingForm;
    const originalSubmitter = pendingSubmitter;
    close(dialog);
    form.requestSubmit(originalSubmitter || undefined);
  });
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault(); qs('#catalog-dialog')?.showModal(); qs('#catalog-search')?.focus();
    }
    if (event.key === 'Escape' && dialog?.open && !busy) close(dialog);
    if (event.key === 'Escape') {
      const current = document.querySelector('dialog[open]');
      if (current && !busy) { event.preventDefault(); close(current); }

      if (document.body.classList.contains('nav-open')) closeNav();
      const profile = qs('.profile-menu');
      if (profile && !profile.hidden) {profile.hidden = true; qs('[data-toggle-profile]')?.setAttribute('aria-expanded', 'false'); qs('[data-toggle-profile]')?.focus();}
    }
    if (event.key === 'Tab' && document.body.classList.contains('nav-open')) {
      const controls = all('a[href],button,summary', qs('.sidebar')).filter(el=>el.getClientRects().length);
      const first = controls[0], last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) {event.preventDefault();last?.focus();}
      else if (!event.shiftKey && document.activeElement === last) {event.preventDefault();first?.focus();}
    }
  });
  all('dialog').forEach(modal => modal.addEventListener('close', () => {
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
  }));
  qs('#catalog-search')?.addEventListener('input', (event) => {
    const query = event.target.value.toLowerCase();
    all('[data-catalog-link]').forEach((a) => { a.hidden = !a.textContent.toLowerCase().includes(query); });
    all('.catalog-group').forEach((group) => { group.hidden = !group.querySelector('[data-catalog-link]:not([hidden])'); });
    const empty = qs('.catalog-empty');
    if (empty) empty.hidden = !!qs('[data-catalog-link]:not([hidden])');
  });
});
