import test from 'node:test';
import assert from 'node:assert/strict';
import {deadlineState} from '../../resources/js/submission-countdown.js';
const now = Date.parse('2026-10-29T12:00:00+05:00');
test('exactly 48 hours is neutral, then turns amber as real time advances', () => {
  const due = now + 48 * 3600000;
  assert.equal(deadlineState(due, now).tone, 'info');
  assert.equal(deadlineState(due, now, 1000).tone, 'warning');
});
test('reaching and passing the deadline turns the counter red', () => {
  assert.deepEqual(deadlineState(now, now), {tone:'danger',label:'Deadline reached'});
  assert.match(deadlineState(now - 3600000, now).label, /Overdue by 1h/);
  assert.equal(deadlineState(now + 1000, now, 2000).tone, 'danger');
});
test('server clock and monotonic elapsed time determine the display', () => {
  assert.match(deadlineState(now + 61000, now, 1000).label, /Due in 1m 0s/);
});
