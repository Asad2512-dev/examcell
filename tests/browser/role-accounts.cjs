const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.BASE_URL || 'http://127.0.0.1:8001';
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const context=await browser.newContext();const page=await context.newPage();const errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const settle=()=>page.evaluate(async()=>{await document.fonts.ready;await Promise.all(document.getAnimations().map(a=>a.finished.catch(()=>{})));});
 try{
  await page.goto(base+'/sign-in');await page.locator('[name=email]').fill(process.env.ADMIN_EMAIL||'admin@gmail.com');await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD||'12345678');
  await Promise.all([page.waitForURL('**/admin/admin-dashboard'),page.locator('[data-auth-form] button[type=submit]').click()]);
  assert.equal(await page.locator('.nav-items a[href="/admin/teachers"]').count(),1);assert.equal(await page.locator('.nav-items a[href="/admin/students"]').count(),1);
  assert(!await page.locator('.sidebar').innerText().then(t=>t.includes('People & Academics')));
  fs.mkdirSync('docs/role-accounts',{recursive:true});let checks=0;
  for(const width of [375,768,1280]){
   await page.setViewportSize({width,height:1000});
   for(const directory of ['teachers','students']){
    let response=await page.goto(base+'/admin/'+directory);assert.equal(response.status(),200);await settle();
    assert.equal(await page.locator(`a[href="/admin/${directory}/create"]`).count(),1);
    const first=await page.locator('tbody a[href$="/edit"]').first().getAttribute('href');
    if(first){response=await page.goto(base+first);assert.equal(response.status(),200);await settle();assert(await page.locator('summary').filter({hasText:'Set a new password'}).count());}
    response=await page.goto(base+'/admin/'+directory+'/create');assert.equal(response.status(),200);await settle();
    assert.equal(await page.locator('[name=role]').count(),0,'roles are assigned by the backend');
    await page.locator('[data-generate-account-password]').click();
    const generated=await page.locator('[name=password]').inputValue();assert(generated.length>=20);assert.equal(await page.locator('[name=password_confirmation]').inputValue(),generated);
    await page.locator('[data-account-reveal]').click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'text');
    await page.locator('[data-account-reveal]').click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'password');
    // Do not leave generated values in screenshots; no accounts are created by this test.
    await page.locator('[name=password]').fill('');await page.locator('[name=password_confirmation]').fill('');
    await page.evaluate(()=>{document.activeElement?.blur();scrollTo({top:0,behavior:'instant'});});await settle();
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await page.screenshot({path:`docs/role-accounts/${directory}-create-${width}.png`,fullPage:true});
    page.once('dialog',dialog=>dialog.accept());
    checks++;
   }
  }
  await page.goto(base+'/admin/teachers');await page.evaluate(()=>document.documentElement.classList.add('dark'));await settle();
  await page.screenshot({path:'docs/role-accounts/teachers-dark.png',fullPage:true});
  assert.deepEqual(errors,[]);
  console.log(`${checks} account form/viewport checks passed; separate navigation, edit screens, generated passwords, show/hide, dark mode and no overflow verified. No production accounts changed.`);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
