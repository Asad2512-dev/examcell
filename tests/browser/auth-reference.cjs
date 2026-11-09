// Authentication visual and interaction checks. Uses existing accounts; no academic writes.
const { chromium } = require('playwright');
const assert = require('node:assert/strict'), fs = require('node:fs');
const base = process.env.BASE_URL || 'http://127.0.0.1:8001';
const out='docs/auth-reference';fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const report={viewports:[],errors:[],assets:[],checks:[]};
 try{
  for(const [width,height] of [[320,844],[375,844],[390,844],[768,1024],[1024,768],[1280,900],[1555,1011],[1920,1080]]){
   const context=await browser.newContext({viewport:{width,height},colorScheme:'light'}),page=await context.newPage();
   page.on('pageerror',e=>report.errors.push(e.message));page.on('response',r=>{if(r.status()>=400)report.assets.push(r.url())});
   assert.equal((await page.goto(base+'/')).status(),200);assert.equal(new URL(page.url()).pathname,'/sign-in');
   await page.evaluate(()=>document.fonts.ready);await page.waitForFunction(()=>document.querySelector('.ex-auth-illustration').complete);
   assert(await page.locator('[data-auth-form]').isVisible());assert(await page.getByRole('heading',{name:'Welcome Back'}).isVisible());
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow at '+width);
   await page.locator('[name=password]').fill('PrivateTestValue!');await page.locator('[data-auth-password="auth-password"]').click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'text');await page.locator('[data-auth-password="auth-password"]').click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'password');await page.locator('[name=password]').fill('');
   await page.locator('[name=remember]').uncheck();assert(!await page.locator('[name=remember]').isChecked());await page.locator('[name=remember]').check();
   assert.equal(await page.locator('.ex-auth-theme,[x-data=themeToggle]').count(),0);assert(!await page.locator('html').evaluate(e=>e.classList.contains('dark')));
   await page.evaluate(()=>{document.activeElement?.blur();scrollTo({top:0,behavior:'instant'})});
   await page.screenshot({path:`${out}/login-${width}.png`,fullPage:true});report.viewports.push(width);
   await page.goto(base+'/forgot-password');assert(await page.locator('[data-auth-form]').isVisible());assert.equal(await page.locator('[name=password]').count(),0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.goto(base+'/reset-password/preview-token?email=sample%40example.test');assert.equal(await page.locator('[name=password_confirmation]').count(),0);assert(await page.getByRole('heading',{name:'Link Unavailable'}).isVisible());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await context.close();
  }
  for(const role of ['admin','teacher','student']){
   const context=await browser.newContext(),page=await context.newPage();await page.goto(base+'/sign-in');await page.locator('[name=email]').fill(role+'@gmail.com');await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD||'12345678');await Promise.all([page.waitForURL(`**/${role}/${role}-dashboard`),page.locator('[data-auth-form] button[type=submit]').click()]);report.checks.push(role+' signs in to its own dashboard');await context.close();
  }
  const context=await browser.newContext({viewport:{width:390,height:844},colorScheme:'dark',reducedMotion:'reduce'}),page=await context.newPage();await page.addInitScript(()=>localStorage.setItem('examcell-theme','dark'));await page.goto(base+'/sign-in');await page.evaluate(()=>document.fonts.ready);assert(!await page.locator('html').evaluate(e=>e.classList.contains('dark')));assert.equal(await page.evaluate(()=>localStorage.getItem('examcell-theme')),'dark');await page.screenshot({path:out+'/login-forced-light-390.png',fullPage:true});assert(await page.locator('[name=email]').isVisible());await context.close();
  assert.deepEqual(report.errors,[]);assert.deepEqual(report.assets,[]);console.log('8 responsive login/recovery layouts, fixed light appearance, password visibility, remember-me and all 3 role sign-ins passed; no overflow, browser errors or failed assets.');
 }finally{fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
