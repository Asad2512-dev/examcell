export function initWorkspaceSimplicity() {
  // Reveal the active section in an overflowing tab strip without scrolling the document.
  document.querySelectorAll('.tabs').forEach(tabs => {
    const active = tabs.querySelector('[aria-current="page"]');
    if (!active) return;
    const reveal = () => {
      const left = active.offsetLeft - tabs.offsetLeft;
      if (left < tabs.scrollLeft || left + active.offsetWidth > tabs.scrollLeft + tabs.clientWidth) {
        tabs.scrollLeft = Math.max(0, left - 12);
      }
    };
    requestAnimationFrame(reveal);
    document.fonts?.ready.then(reveal);
  });
  const menus = [...document.querySelectorAll('.export-menu')];
  document.addEventListener('click', event => {
    menus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    menus.filter(menu => menu.open).forEach(menu => {
      menu.open = false;
      menu.querySelector('summary')?.focus();
    });
  });
}
