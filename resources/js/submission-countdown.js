import { uiText } from './core/content.js';

// A server timestamp plus monotonic browser elapsed time avoids a skewed client clock.
export function deadlineState(deadlineMs, serverNowMs, elapsedMs = 0) {
  const remaining = deadlineMs - serverNowMs - elapsedMs;
  const overdue = remaining <= 0;
  const seconds = Math.max(0, Math.ceil(Math.abs(remaining) / 1000));
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor(seconds % 86400 / 3600);
  const minutes = Math.floor(seconds % 3600 / 60);
  const tail = seconds % 60;
  const duration = (days ? `${days}d ` : '') + (hours || days ? `${hours}h ` : '') + `${minutes}m ${tail}s`;
  return {
    tone: overdue ? 'danger' : remaining < 48 * 3600000 ? 'warning' : 'info',
    label: remaining === 0 ? uiText('common.runtime.dates.deadline_reached', 'Deadline reached') : `${uiText(overdue ? 'common.runtime.dates.overdue_by' : 'common.runtime.dates.due_in', overdue ? 'Overdue by' : 'Due in')} ${duration}`,
  };
}

export function startSubmissionCountdowns(root = document) {
  const nodes = [...root.querySelectorAll('[data-submission-countdown]')].map(element => ({
    element,
    deadline: Date.parse(element.dataset.deadline),
    serverNow: Date.parse(element.dataset.serverNow),
    started: performance.now(),
  })).filter(item => Number.isFinite(item.deadline) && Number.isFinite(item.serverNow));
  if (!nodes.length) return;
  const update = () => nodes.forEach(item => {
    const state = deadlineState(item.deadline, item.serverNow, performance.now() - item.started);
    const badge = item.element.querySelector('[data-countdown-badge]');
    badge.classList.remove('badge-info', 'badge-warning', 'badge-danger');
    badge.classList.add(`badge-${state.tone}`);
    item.element.querySelector('[data-countdown-label]').textContent = state.label;
  });
  update();
  const timer = setInterval(update, 1000);
  window.addEventListener('pagehide', () => clearInterval(timer), {once: true});
}
