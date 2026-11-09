function readContent() {
  const element = document.getElementById('examcell-content');
  if (!element) return {};
  try { return JSON.parse(element.textContent || '{}'); } catch { return {}; }
}

const runtime = typeof window === 'undefined' ? globalThis : window;
const content = runtime.examcellContent && typeof runtime.examcellContent === 'object' ? runtime.examcellContent : (typeof document === 'undefined' ? {} : readContent());

/** Resolve a safe, public UI string from the server-provided content dictionary. */
export function uiText(key, fallback = key, replacements = {}) {
  const value = String(key || '').split('.').reduce((current, part) => current && typeof current === 'object' ? current[part] : undefined, content);
  if (typeof value !== 'string') return fallback;
  return Object.entries(replacements).reduce((text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement)), value);
}

runtime.examcellText = uiText;
