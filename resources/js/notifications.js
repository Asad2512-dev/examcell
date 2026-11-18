export function initNotifications() {
  const pending = new Set();
  const updateCount = (count) => {
    document.querySelectorAll("[data-unread-count]").forEach((badge) => {
      badge.textContent = `${count} unread`;
    });
    document.querySelectorAll("[data-open-notifications]").forEach((button) => {
      button.setAttribute("aria-label", `Notifications, ${count} unread`);
      button
        .querySelector("[data-unread-dot]")
        ?.toggleAttribute("hidden", count === 0);
    });
    document.querySelectorAll("[data-notice-read-all]").forEach((form) => {
      form.hidden = count === 0;
    });
  };
  document.addEventListener("click", (event) => {
    const button = event.target.closest?.("[data-notice-expand]");
    if (!button) return;
    const expanded = button.getAttribute("aria-expanded") !== "true";
    button.setAttribute("aria-expanded", String(expanded));
    button.textContent = expanded ? "Show less" : "Show more";
    button
      .closest("[data-notice-id]")
      .classList.toggle("notice-expanded", expanded);
  });
  document.addEventListener("submit", async (event) => {
    const form = event.target;
    if (!form.matches("[data-notice-read], [data-notice-read-all]")) return;
    if (event.defaultPrevented) return;
    event.preventDefault();
    const container =
      form.closest("dialog") ||
      form.closest(".notification-list") ||
      document.body;
    let feedback = container.querySelector("[data-notice-feedback]");
    if (!feedback) {
      feedback = document.createElement("p");
      feedback.className = "notice-feedback";
      feedback.dataset.noticeFeedback = "";
      feedback.setAttribute("role", "status");
      feedback.setAttribute("aria-live", "polite");
      container.prepend(feedback);
    }
    const originRow = form.closest("[data-notice-id]");
    const all = form.hasAttribute("data-notice-read-all");
    const id = form.closest("[data-notice-id]")?.dataset.noticeId;
    const key = all ? "all" : id;
    if (pending.size) return;
    pending.add(key);
    const buttons = [
      ...document.querySelectorAll(
        '[data-notice-read] button[type="submit"], [data-notice-read-all] button[type="submit"]',
      ),
    ];
    buttons.forEach((control) => {
      control.disabled = true;
    });
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    const spinner = document.createElement("span");
    spinner.className = "submit-spinner";
    spinner.setAttribute("aria-hidden", "true");
    button.prepend(spinner);
    if (feedback) feedback.textContent = "";
    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        signal: AbortSignal.timeout(15000),
      });
      if (!response.ok || response.redirected)
        throw new Error("Request failed");
      const data = await response.json();
      if (!Number.isInteger(data.unread_count) || data.unread_count < 0)
        throw new Error("Invalid response");
      document.querySelectorAll("[data-notice-id]").forEach((item) => {
        if (!all && item.dataset.noticeId !== id) return;
        item.querySelector("[data-notice-new]")?.remove();
        item.querySelector("[data-notice-read]")?.remove();
        item.classList.add("notice-is-read");
      });
      updateCount(data.unread_count);
      if (feedback)
        feedback.textContent = all
          ? "All notifications marked read."
          : "Notification marked read.";
    } catch {
      if (feedback)
        feedback.textContent =
          "Could not update notifications. Please try again.";
    } finally {
      pending.delete(key);
      buttons.forEach((control) => {
        control.disabled = false;
      });
      button.removeAttribute("aria-busy");
      spinner.remove();
      if (!form.isConnected && !pending.size) {
        if (container.matches("dialog[open]")) {
          container.querySelector("[data-close-dialog]")?.focus();
        } else if (originRow) {
          originRow.tabIndex = -1;
          originRow.focus();
        }
      }
    }
  });
}
