import test from "node:test";
import assert from "node:assert/strict";
import { registerAiProgress } from "../../resources/js/ai-progress.js";

test("AI status requests never overlap and stop polling after destruction", async (context) => {
  let factory;
  registerAiProgress({
    data: (_, callback) => {
      factory = callback;
    },
  });
  context.mock.method(globalThis, "fetch", async () => {
    await pending;
    return {
      ok: true,
      status: 200,
      json: async () => ({
        progress: 50,
        message: "Working",
        status: "running",
      }),
    };
  });
  let release;
  const pending = new Promise((resolve) => {
    release = resolve;
  });
  const previousDocument = globalThis.document;
  globalThis.document = {
    hidden: false,
    addEventListener() {},
    removeEventListener() {},
  };
  const progress = factory({ id: 4, progress: 0, message: "Queued" });
  try {
    const first = progress.poll();
    await progress.poll();
    assert.equal(fetch.mock.callCount(), 1);
    release();
    await first;
    assert.equal(progress.p, 50);
    assert.equal(progress.m, "Working");
    progress.destroy();
    await progress.poll();
    assert.equal(fetch.mock.callCount(), 1);
  } finally {
    progress.destroy();
    globalThis.document = previousDocument;
  }
});

test("AI polling pauses in hidden tabs", async () => {
  let factory;
  registerAiProgress({
    data: (_, callback) => {
      factory = callback;
    },
  });
  const previousDocument = globalThis.document;
  globalThis.document = { hidden: true, removeEventListener() {} };
  const progress = factory({ id: 4, progress: 0, message: "Queued" });
  try {
    await progress.poll();
    assert.equal(progress.controller, null);
    assert.equal(progress.timer, null);
  } finally {
    progress.destroy();
    globalThis.document = previousDocument;
  }
});
