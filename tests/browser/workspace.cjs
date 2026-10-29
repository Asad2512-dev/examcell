// Read-only visual/route audit against existing accounts; no academic records are changed.
const { chromium } = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const screens = JSON.parse(fs.readFileSync('resources/screens.json', 'utf8'));
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
  const report = {routes:[], responsive:[], errors:[]};
  try {
    for (const role of ['admin','teacher','student']) {
      const context = await browser.newContext({viewport:{width:1440,height:1000}});
      const page = await context.newPage();
      page.on('pageerror', error => report.errors.push({role, message:error.message}));
      await page.goto(base+'/sign-in');
      await page.locator('[name=email]').fill(`${role}@gmail.com`);
      await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD || '12345678');
      await Promise.all([page.waitForURL(`**/${role}/${role}-dashboard`),page.locator('[data-auth-form] button[type=submit]').click()]);
      const roleScreens = screens.filter(s => s.role === role && s.kind !== 'auth');
      for (const [index, screen] of roleScreens.entries()) {
        const response = await page.goto(`${base}/${role}/${screen.slug}`);
        await page.evaluate(()=>Promise.all(document.getAnimations().map(animation=>animation.finished.catch(()=>{}))));
        const geometry = await page.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,system:!!document.querySelector('link[href*="workspace.css"]'), current:document.querySelectorAll('[aria-current="page"]').length}));
        await page.setViewportSize({width:390,height:844});
        await page.evaluate(async()=>{void document.body.offsetWidth;await Promise.all(document.getAnimations().map(animation=>animation.finished.catch(()=>{})))});
        const mobileScroll = await page.evaluate(()=>document.documentElement.scrollWidth);
        await page.setViewportSize({width:1440,height:1000});
        await page.evaluate(async()=>{void document.body.offsetWidth;await Promise.all(document.getAnimations().map(animation=>animation.finished.catch(()=>{})))});
        report.routes.push({role,slug:screen.slug,module:screen.module,title:screen.title,kind:screen.kind,status:response.status(),mobileScroll,...geometry});
        if ((index+1)%25 === 0) console.log(`${role}: ${index+1}/${roleScreens.length} pages checked`);
      }
      for (const width of [320,360,375,390,414,768,820,1024,1280,1440,1920]) {
        await page.setViewportSize({width,height:1000});
        await page.goto(`${base}/${role}/${role}-dashboard`);
        await page.evaluate(()=>Promise.all(document.getAnimations().map(animation=>animation.finished.catch(()=>{}))));
        const geometry = await page.evaluate(() => {
          const boxes = selector => [...document.querySelectorAll(selector)].map(e => {const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,width:r.width,height:r.height}});
          return {scroll:document.documentElement.scrollWidth,kpis:boxes('.dashboard-kpis>.card'),middle:boxes('.dashboard-middle>.card'),bottom:boxes('.dashboard-bottom-grid>.card')};
        });
        assert(geometry.scroll<=width+1,`${role} overflow at ${width}`);
        if (width>1050) for (const group of ['kpis','middle','bottom']) {
          assert(Math.max(...geometry[group].map(r=>r.top))-Math.min(...geometry[group].map(r=>r.top))<1,`${role} ${group} top alignment`);
          assert(Math.max(...geometry[group].map(r=>r.bottom))-Math.min(...geometry[group].map(r=>r.bottom))<1,`${role} ${group} bottom alignment`);
        }
        report.responsive.push({role,width,...geometry});
        if ([390,1440].includes(width)) await page.screenshot({path:`docs/${role}-workspace-${width}.png`,fullPage:true});
      }
      await page.setViewportSize({width:390,height:844});
      await page.locator('[data-open-nav]').click();
      assert(await page.locator('body').evaluate(el=>el.classList.contains('nav-open')));
      await page.keyboard.press('Escape');
      assert(!(await page.locator('body').evaluate(el=>el.classList.contains('nav-open'))));
      await page.locator('[data-open-notifications]').click();
      assert(await page.locator('#notification-dialog').evaluate(el=>el.open));
      const bounds=await page.locator('#notification-dialog').boundingBox();
      assert(bounds.x>=0 && bounds.x+bounds.width<=391);
      await page.keyboard.press('Escape');
      await page.emulateMedia({reducedMotion:'reduce'});
      assert.equal(await page.locator('#main-content').evaluate(el=>getComputedStyle(el).animationName),'none');
      await context.close();
      console.log(`${role}: route and responsive audit complete`);
    }
    fs.writeFileSync('docs/workspace-qa.json',JSON.stringify(report,null,2));
    fs.writeFileSync('docs/WORKSPACE-ROUTE-AUDIT.md', '# Workspace route audit\n\nEvery authenticated screen uses `layouts.app` and the shared `workspace.css` design system. Generated by `node tests/browser/workspace.cjs` against the existing local accounts. Page checks cover rendering, stylesheet inclusion, and viewport overflow at 1440px and 390px; they do not submit each business workflow.\n\n| Role | Route | Page | Type | HTTP | Desktop fit | Mobile fit |\n| --- | --- | --- | --- | --- | --- | --- |\n'+report.routes.map(r=>`| ${r.role} | /${r.role}/${r.slug} | ${r.title} | ${r.kind} | ${r.status} | ${r.scroll<=1441?'Pass':'Fail'} | ${r.mobileScroll<=391?'Pass':'Fail'} |`).join('\n')+'\n');
    assert.deepEqual(report.errors,[]);
    assert.deepEqual(report.routes.filter(r=>r.status!==200 || !r.system || r.scroll>r.width+1 || r.mobileScroll>391),[],'Route rendering or overflow failures; see docs/workspace-qa.json');
    console.log(`PASS: ${report.routes.length} pages, ${report.responsive.length} responsive dashboards, keyboard navigation, dialogs and reduced motion`);
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1});
