/** Feedback for real navigations; canceled events and AJAX forms keep their own state. */
export function initLoadingFeedback() {
  if (document.getElementById("page-loading")) return;

  const indicator = document.createElement("div");
  indicator.id = "page-loading";
  indicator.className = "page-loading";
  indicator.hidden = true;
  indicator.setAttribute("role", "status");
  indicator.setAttribute("aria-live", "polite");
  const label = document.createElement("span");
  label.className = "sr-only";
  label.textContent = "Loading, please wait…";
  indicator.append(label);
  document.body.append(indicator);

  let timer;
  const busyForms = new Map();
  const show = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      indicator.hidden = false;
    }, 120);
  };
  const reset = () => {
    clearTimeout(timer);
    indicator.hidden = true;
    for (const [form, button] of busyForms) {
      form.removeAttribute("aria-busy");
      if (button) {
        button.classList.remove("is-submitting");
        button.removeAttribute("aria-disabled");
        button.querySelector(".submit-spinner")?.remove();
      }
    }
    busyForms.clear();
  };

  document.addEventListener("click", (event) => {
    const link = event.target.closest?.("a[href]");
    if (
      !link ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    )
      return;
    if (
      link.hasAttribute("download") ||
      link.hasAttribute("data-no-loading") ||
      (link.target && link.target !== "_self")
    )
      return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol))
      return;
    // Anchors and same-page controls do not start a new document request.
    if (url.pathname === location.pathname && url.search === location.search)
      return;
    queueMicrotask(() => {
      if (!event.defaultPrevented) show();
    });
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (event.defaultPrevented) return;
    const button = event.submitter;
    const target = button?.formTarget || form.target;
    const method = button?.getAttribute("formmethod") || form.method;
    if (
      method === "dialog" ||
      (target && target !== "_self") ||
      form.hasAttribute("data-no-loading")
    )
      return;
    if (busyForms.has(form)) {
      event.preventDefault();
      return;
    }
    queueMicrotask(() => {
      if (event.defaultPrevented) return;
      busyForms.set(form, button);
      form.setAttribute("aria-busy", "true");
      if (button) {
        // Keep the submitter enabled so its name/value is sent with the form.
        button.setAttribute("aria-disabled", "true");
        button.classList.add("is-submitting");
        if (button.tagName === "BUTTON") {
          const spinner = document.createElement("span");
          spinner.className = "submit-spinner";
          spinner.setAttribute("aria-hidden", "true");
          button.prepend(spinner);
        }
      }
      show();
    });
  });

  // Restore controls when returning through the browser's back/forward cache.
  window.addEventListener("pageshow", reset);
  window.addEventListener("pagehide", reset);
  window.addEventListener("offline", reset);
  window.addEventListener("beforeunload", (event) => {
    queueMicrotask(() => {
      // Unsaved-change prompts can cancel navigation without a pageshow event.
      if (event.defaultPrevented || event.returnValue) setTimeout(reset, 0);
    });
  });
}
