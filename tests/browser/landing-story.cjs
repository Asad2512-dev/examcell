// Public-only checks for the short landing page. No account writes or OpenAI calls.
const assert=require('node:assert/strict'),fs=require('node:fs');
const {chromium}=require('playwright');
const base=(process.env.BASE_URL||'http://127.0.0.1:8001').replace(/\/$/,'')+'/about';
const out='docs/landing-compact';fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const report={checks:[],errors:[],assets:[],heights:{}};
 const pass=text=>{report.checks.push(text);console.log('PASS',text)};
 try{
  for(const width of [375,768,1280,1920]){
   const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
   page.on('pageerror',error=>report.errors.push(error.message));
   page.on('response',response=>{if(response.status()>=400)report.assets.push(response.url())});
   page.on('request',request=>assert.notEqual(request.method(),'POST'));
   assert.equal((await page.goto(base)).status(),200);
   await page.waitForFunction(()=>document.body.dataset.storyInitialized==='true');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(1200);
   assert.equal(await page.locator('main>section').count(),6);
   assert.equal(await page.locator('[data-feature-step]').count(),6);
   assert.equal(await page.locator('.ls-faq-list details').count(),4);
   assert.equal(await page.locator('h1').count(),1);
   assert.equal((await page.locator('h1').innerText()).replace(/\s+/g,' ').trim(),'From exam planning to published results. One connected workspace.');
   assert.equal(await page.locator('.pin-spacer').count(),0);
   const max=await page.evaluate(()=>document.documentElement.scrollHeight-innerHeight);report.heights[width]=max+900;
   assert(max+900<(width<768?6000:5000),'Landing must remain compact at '+width);
   for(let y=0;y<=max+500;y+=500){await page.evaluate(y=>scrollTo({top:y,behavior:'instant'}),y);await page.waitForTimeout(60);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));}
   for(const id of ['workspaces','seating','capabilities','overview','faq']){await page.evaluate(id=>document.getElementById(id).scrollIntoView({behavior:'instant'}),id);await page.waitForTimeout(200);assert(await page.locator('#'+id+' h1,#'+id+' h2').isVisible());}
   for(const role of ['teacher','student','admin']){await page.locator('[data-workspace-tab="'+role+'"]').click();assert(await page.locator('[data-workspace-panel="'+role+'"]').isVisible());assert.equal(await page.locator('[data-workspace-panel]:visible').count(),1);}
   await page.locator('#tab-admin').focus();await page.keyboard.press('ArrowRight');assert.equal(await page.locator('[data-workspace-tab][aria-selected=true]').getAttribute('data-workspace-tab'),'teacher');await page.keyboard.press('End');assert.equal(await page.locator('[data-workspace-tab][aria-selected=true]').getAttribute('data-workspace-tab'),'student');await page.keyboard.press('Home');
   await page.locator('#faq').scrollIntoViewIfNeeded();await page.locator('.ls-faq-list summary').first().focus();await page.keyboard.press('Enter');assert.equal(await page.locator('.ls-faq-list details[open]').count(),1);await page.keyboard.press('Enter');
   for(const [id,target] of [['overview','overview'],['capabilities','capabilities'],['seating','seating'],['workspaces','workspaces'],['faq','faq']]){await page.evaluate(id=>document.getElementById(id).scrollIntoView({behavior:'instant',block:'start'}),id);await page.waitForTimeout(160);assert.equal(await page.locator('.ls-desktop-nav [aria-current=location]').getAttribute('href'),'#'+target);}
   if(width<1024){await page.locator('.ls-mobile-nav summary').click();await page.locator('.ls-mobile-nav a[href="#seating"]').click();await page.waitForTimeout(300);assert(!await page.locator('main').evaluate(e=>e.inert));assert.equal(new URL(page.url()).hash,'');}
   const signIns=await page.locator('a').evaluateAll(links=>links.filter(a=>/sign in/i.test(a.textContent)).map(a=>new URL(a.href).pathname));assert(signIns.length>=5);assert(signIns.every(path=>path==='/sign-in'));
   // Load the active product image before the full-page review capture.
   await page.locator('#workspaces').scrollIntoViewIfNeeded();await page.waitForFunction(()=>{const img=document.querySelector('[data-workspace-panel]:not([hidden]) img');return img.complete&&img.naturalWidth>0});await page.waitForTimeout(500);
   await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));await page.waitForTimeout(200);await page.screenshot({path:`${out}/full-${width}.png`,fullPage:true});
   pass(width+': short layout, forward/backward scroll, role tabs, FAQ, active navigation and clean sign-in links');await context.close();
  }
  for(const options of [{reducedMotion:'reduce'},{javaScriptEnabled:false}]){
   const context=await browser.newContext({viewport:{width:375,height:900},...options}),page=await context.newPage();page.on('pageerror',e=>report.errors.push(e.message));await page.goto(base);assert(await page.locator('h1').isVisible());assert(await page.locator('.ls-login').isVisible());assert.equal(await page.locator('.pin-spacer').count(),0);
   if(options.reducedMotion)await page.waitForFunction(()=>document.body.dataset.motion==='reduced');else assert.equal(await page.locator('[data-workspace-panel]:visible').count(),3);
   await page.locator('.ls-faq-list summary').first().click();assert(await page.locator('.ls-faq-list details[open]').count());assert(await page.locator('a[href^="#"]').evaluateAll(links=>links.every(a=>document.querySelector(a.getAttribute('href')))));pass(options.reducedMotion?'Reduced motion: usable static content and controls':'No JavaScript: all role content, anchors and native FAQ available');await context.close();
  }
  const page=await browser.newPage();await page.goto(base+'#results');await page.waitForFunction(()=>document.body.dataset.storyInitialized==='true');await page.waitForTimeout(500);assert.equal(new URL(page.url()).hash,'');assert(await page.locator('#capabilities h2').isVisible());await page.close();pass('Old section links normalize to the shorter page');
  assert.deepEqual(report.errors,[]);assert.deepEqual(report.assets,[]);pass('No browser errors, failed assets or public writes');
 }finally{fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
