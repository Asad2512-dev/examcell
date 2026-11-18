const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");

(async () => {
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROMIUM_EXECUTABLE_PATH
      ? { executablePath: process.env.CHROMIUM_EXECUTABLE_PATH }
      : { channel: "chrome" }),
  });
  try {
    const page = await browser.newPage();
    const html = `<a href="/next" id="next">Next page</a>
      <a href="#section" id="anchor">Section</a>
      <form action="/save" method="post"><input name="title" value="Example">
      <button name="intent" value="save" type="submit">Save</button></form>`;
    await page.route("http://examcell.test/**", (route) =>
      route.fulfill({ body: html, contentType: "text/html" }),
    );
    const source = fs.readFileSync(
      path.join(__dirname, "../../resources/js/loading.js"),
      "utf8",
    );
    async function open() {
      await page.goto("http://examcell.test/");
      const manifest = JSON.parse(
        fs.readFileSync(
          path.join(__dirname, "../../public/build/manifest.json"),
          "utf8",
        ),
      );
      await page.addStyleTag({
        content: fs.readFileSync(
          path.join(
            __dirname,
            "../../public/build",
            manifest["resources/css/app.css"].file,
          ),
          "utf8",
        ),
      });
      await page.addScriptTag({
        type: "module",
        content:
          source.replace("export function", "function") +
          "\ninitLoadingFeedback();",
      });
      await page.waitForSelector("#page-loading", { state: "attached" });
    }
    await open();
    await page.click("#anchor");
    await page.waitForTimeout(180);
    assert.equal(
      await page.locator("#page-loading").isVisible(),
      false,
      "Anchor links must not show a loader",
    );
    await page.evaluate(() =>
      document
        .querySelector("form")
        .addEventListener("submit", (event) => event.preventDefault()),
    );
    await page.click("button");
    assert.equal(
      await page.locator(".submit-spinner").count(),
      0,
      "Canceled/AJAX forms must not enter a busy state",
    );

    await open();
    await page.evaluate(() => {
      const form = document.querySelector("form");
      form.dispatchEvent(
        new SubmitEvent("submit", {
          bubbles: true,
          cancelable: true,
          submitter: form.querySelector("button"),
        }),
      );
    });
    await page.waitForSelector(".submit-spinner");
    assert.equal(
      await page.locator("button").evaluate((button) => !button.disabled),
      true,
    );
    assert.equal(await page.locator("form").getAttribute("aria-busy"), "true");
    await page.waitForSelector("#page-loading", { state: "visible" });
    const duplicatePrevented = await page.evaluate(() => {
      const form = document.querySelector("form");
      const event = new SubmitEvent("submit", {
        bubbles: true,
        cancelable: true,
        submitter: form.querySelector("button"),
      });
      form.dispatchEvent(event);
      return event.defaultPrevented;
    });
    assert.equal(
      duplicatePrevented,
      true,
      "Repeated submits must be prevented",
    );
    await page.evaluate(() =>
      window.dispatchEvent(
        new PageTransitionEvent("pageshow", { persisted: true }),
      ),
    );
    assert.equal(
      await page.locator(".submit-spinner").count(),
      0,
      "Back navigation must restore controls",
    );
    assert.equal(await page.locator("form").getAttribute("aria-busy"), null);
    await page.evaluate(() => {
      const form = document.querySelector("form");
      form.dispatchEvent(
        new SubmitEvent("submit", {
          bubbles: true,
          cancelable: true,
          submitter: form.querySelector("button"),
        }),
      );
    });
    await page.waitForSelector(".submit-spinner");
    await page.evaluate(() => {
      window.addEventListener(
        "beforeunload",
        (event) => event.preventDefault(),
        { once: true },
      );
      window.dispatchEvent(new Event("beforeunload", { cancelable: true }));
    });
    await page.waitForSelector(".submit-spinner", { state: "detached" });
    assert.equal(
      await page.locator("#page-loading").isVisible(),
      false,
      "Canceled navigation must reset feedback",
    );
    const intent = await page.evaluate(() => {
      const form = document.querySelector("form");
      return new FormData(form, form.querySelector("button")).get("intent");
    });
    assert.equal(
      intent,
      "save",
      "The clicked submitter's value must be preserved",
    );
    console.log(
      "PASS loading feedback: anchors, canceled forms, spinner, submit payload, duplicates, and Back restoration",
    );
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
