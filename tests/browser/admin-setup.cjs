// Read-only browser verification. Academic mutations are covered by isolated PHPUnit tests.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.BASE_URL || 'http://127.0.0.1:8001';
(async () => {
  const browser = await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
  const context = await browser.newContext({viewport:{width:1440,height:1000}});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const settle = () => page.evaluate(async () => { await document.fonts.ready; await Promise.all(document.getAnimations().map(a => a.finished.catch(()=>{}))); });
  try {
    await page.goto(base+'/sign-in');
    await page.locator('[name=email]').fill(process.env.ADMIN_EMAIL || 'admin@gmail.com');
    await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD || '12345678');
    await Promise.all([page.waitForURL('**/admin/admin-dashboard'),page.locator('[data-auth-form] button[type=submit]').click()]);
    const routes = ['admin-dashboard','academic-setup?step=session','academic-setup?step=departments','academic-setup?step=courses','academic-setup?step=teachers','academic-setup?step=students','academic-setup?step=review','assistant','exams','seating','invigilation','marks','reports','teachers','students','rooms','settings'];
    let checks = 0;
    fs.mkdirSync('docs/admin-setup',{recursive:true});
    for (const width of [375,768,1280,1920]) {
      await page.setViewportSize({width,height:1000});
      for (const route of routes) {
        const response = await page.goto(base+'/admin/'+route);
        assert.equal(response.status(),200,route);
        await settle();
        const geometry = await page.evaluate(() => {
          const main = document.querySelector('.app-main').getBoundingClientRect();
          return {width:innerWidth,scroll:document.documentElement.scrollWidth,left:main.left,mainWidth:main.width,navFont:parseFloat(getComputedStyle(document.querySelector('.nav-item')).fontSize),h1:parseFloat(getComputedStyle(document.querySelector('h1')).fontSize)};
        });
        assert(geometry.scroll<=width+1,`${route} overflows at ${width}: ${geometry.scroll}`);
        if(width<=768) assert(geometry.left<1,`${route} sidebar pushes mobile content`);
        assert.equal(geometry.navFont,width>768&&width<=1050?14:15,`${route} original navigation +3px at ${width}`);
        assert.equal(geometry.h1,width<=768?28:31,`${route} original heading +3px at ${width}`);
        checks++;
      }
      await page.goto(base+'/admin/academic-setup?step=teachers');
      await settle();
      assert.equal(await page.locator('.setup-progress [aria-current=step]').count(),1);
      await page.locator('input[name=mode][value=new]').check();
      await page.locator('input[name=email]').waitFor({state:'visible'});
      assert(await page.locator('select[name=account_id]').isDisabled());
      await page.locator('input[name=mode][value=existing]').check();
      await page.waitForFunction(()=>document.querySelector('input[name=email]').disabled);
      const sections = await page.locator('select[name=section_id] option').evaluateAll(options=>options.filter(o=>o.value).map(o=>o.value));
      if(sections.length){
        await page.locator('select[name=section_id]').selectOption(sections[0]);
        await page.locator('input[aria-label="Find a course"]').fill('zzzzzzzz');
        await page.waitForFunction(()=>[...document.querySelectorAll('.setup-course-list label')].every(e=>getComputedStyle(e).display==='none'));
        await page.locator('input[aria-label="Find a course"]').fill('');
      }
      await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:"instant"});});
      await settle();
      await page.screenshot({path:`docs/admin-setup/teachers-${width}.png`,fullPage:true});
      // Discard only unsaved UI interactions, no request was submitted.
      page.once('dialog',dialog=>dialog.accept());
      await page.goto(base+'/admin/academic-setup?step=session');
      await settle();
      if(width===375){
        await page.locator('[data-open-nav]').click(); await settle();
        assert(await page.locator('.sidebar a[aria-current=page]').isVisible());
        await page.keyboard.press('Escape'); await settle();
      }
    }
    await page.setViewportSize({width:1280,height:1000});
    await page.goto(base+'/admin/academic-setup?step=review');
    await page.evaluate(()=>document.documentElement.classList.add('dark'));
    await settle();
    await page.screenshot({path:'docs/admin-setup/review-dark-1280.png',fullPage:true});
    await page.locator('[data-open-catalog]').click();
    assert(await page.locator('#catalog-dialog a[href="/admin/academic-setup"]').isVisible());
    await page.keyboard.press('Escape');
    assert.deepEqual(errors,[]);
    console.log(`${checks} admin page/viewport checks passed; account modes, course search, mobile navigation, dark mode and command palette verified; no browser errors.`);
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
