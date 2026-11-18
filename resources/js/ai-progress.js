/** One poll at a time; pause hidden tabs and back off after network failures. */
export function registerAiProgress(Alpine) {
  Alpine.data("aiRunProgress", (run) => ({
    p: run.progress,
    m: run.message,
    timer: null,
    controller: null,
    stopped: false,
    failures: 0,
    onVisible: null,
    init() {
      this.onVisible = () => {
        if (!document.hidden && !this.stopped && !this.controller) {
          clearTimeout(this.timer);
          this.poll();
        }
      };
      document.addEventListener("visibilitychange", this.onVisible);
      this.timer = setTimeout(() => this.poll(), 1000);
    },
    async poll() {
      if (this.stopped || document.hidden || this.controller) return;
      this.controller = new AbortController();
      const timeout = setTimeout(() => this.controller?.abort(), 10000);
      try {
        const response = await fetch(`/ai/runs/${run.id}/status`, {
          headers: { Accept: "application/json" },
          signal: this.controller.signal,
        });
        if ([401, 403, 404].includes(response.status)) {
          this.stopped = true;
          this.m =
            "This run is no longer available. Refresh the page to continue.";
          return;
        }
        if (!response.ok) throw new Error("Status unavailable");
        const status = await response.json();
        this.p = status.progress;
        this.m = status.message;
        this.failures = 0;
        if (!["queued", "running"].includes(status.status)) {
          this.stopped = true;
          location.reload();
        }
      } catch {
        if (!this.stopped) this.failures++;
      } finally {
        clearTimeout(timeout);
        this.controller = null;
        if (!this.stopped && !document.hidden) {
          this.timer = setTimeout(
            () => this.poll(),
            Math.min(15000, 2000 * 2 ** this.failures),
          );
        }
      }
    },
    destroy() {
      this.stopped = true;
      clearTimeout(this.timer);
      this.controller?.abort();
      document.removeEventListener("visibilitychange", this.onVisible);
    },
  }));
}
