// Public-only responsive and interaction QA. Does not write academic data or contact OpenAI.
const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs');
const base=(process.env.BASE_URL||'http://127.0.0.1:8001').replace(/\/$/,'')+'/about';
const out='docs/landing-responsive';fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const report={viewports:[],checks:[],errors:[],failedAssets:[]};
 const widths=[320,360,375,390,430,768,1024,1280,1440,1920];
 const sections=['overview','capabilities','seating','workspaces','faq'];
 const pass=name=>{report.checks.push(name);console.log('PASS',name)};
 try{
 for(const width of widths){
  const context=await browser.newContext({viewport:{width,height:width<768?844:1000},hasTouch:width<=1024,isMobile:width<768});
  const page=await context.newPage();page.setDefaultTimeout(8000);
  page.on('pageerror',e=>report.errors.push({width,message:e.message}));
  page.on('response',r=>{if(r.status()>=400)report.failedAssets.push({width,url:r.url(),status:r.status()})});
  page.on('request',r=>assert.notEqual(r.method(),'POST','Public demos must not send mutations'));
  assert.equal((await page.goto(base)).status(),200);
  await page.waitForFunction(()=>document.body.dataset.storyInitialized==='true');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(1200);
  const overflow=()=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
  assert(!await overflow(),'Hero overflow '+width);
  assert(await page.locator('.ls-login').isVisible());
  const tapTargets=await page.locator('.ls-login,.ls-header>a,.ls-hero-copy .ls-button').evaluateAll(nodes=>nodes.filter(n=>n.checkVisibility()).every(n=>n.getBoundingClientRect().height>=43));assert(tapTargets,'Small primary target '+width);
  if(width<=1024)assert.equal(await page.locator('.pin-spacer').count(),0,'Tablet and phone must not pin');
  if([320,390,768,1024,1440].includes(width))await page.screenshot({path:out+'/hero-'+width+'.png'});
  for(const id of sections){
   await page.evaluate(id=>document.getElementById(id).scrollIntoView({behavior:'instant',block:'start'}),id);await page.waitForTimeout(180);
   assert(!await overflow(),id+' page overflow '+width);
   const headings=await page.locator('#'+id+' h1,#'+id+' h2,#'+id+' h3').evaluateAll(nodes=>nodes.filter(n=>n.checkVisibility()).every(n=>n.scrollWidth<=n.clientWidth+1));assert(headings,'Clipped heading '+id+' '+width);
   if([320,390,768,1024].includes(width)&&['seating','workspaces'].includes(id)){await page.waitForTimeout(300);await page.screenshot({path:out+'/'+id+'-'+width+'.png'});}
  }
  // Every chapter remains reachable in either direction and by a fast jump.
  for(const id of ['capabilities','seating','overview','faq']){await page.evaluate(id=>document.getElementById(id).scrollIntoView({behavior:'instant',block:'start'}),id);await page.waitForTimeout(80);assert(!await overflow());}
  if(width<1024){
   await page.evaluate(()=>window.scrollTo({top:500,behavior:'instant'}));
   await page.locator('.ls-mobile-nav summary').click();await page.waitForFunction(()=>document.documentElement.classList.contains('ls-menu-open'));
   assert(await page.locator('main').evaluate(el=>el.inert));
   const initial=await page.evaluate(()=>scrollY);await page.mouse.move(width-8,800);await page.mouse.wheel(0,350);await page.waitForTimeout(120);assert.equal(await page.evaluate(()=>scrollY),initial,'Menu leaked background scroll');
   await page.keyboard.press('Escape');assert.equal(await page.locator('.ls-mobile-nav').getAttribute('open'),null);assert(!await page.locator('main').evaluate(el=>el.inert));
   assert(await page.locator('.ls-mobile-nav summary').evaluate(el=>el===document.activeElement));
   await page.keyboard.press('Enter');await page.waitForFunction(()=>document.documentElement.classList.contains('ls-menu-open'));
   for(let i=0;i<12;i++){await page.keyboard.press('Tab');assert(await page.evaluate(()=>!!document.activeElement.closest('.ls-header')),'Menu focus escaped')}
   await page.locator('.ls-mobile-nav a[href="#seating"]').click();await page.waitForFunction(()=>!document.querySelector('main').inert);assert.equal(await page.locator('.ls-mobile-nav').getAttribute('open'),null);
   pass(width+': navigation scroll lock, Escape, focus loop and close after selection');
  }
  // Synthetic seating controls: search, unavailable/accessibility states, keyboard and touch.
  await page.evaluate(()=>document.getElementById('seating').scrollIntoView({behavior:'instant',block:'start'}));
  const figure=page.locator('.ls-room-figure');
  await figure.locator('input').fill('DEMO-024');await figure.getByRole('button',{name:'Locate',exact:true}).click();
  assert.match(await figure.locator('[role=status]').textContent(),/D6.*Unavailable/);
  if(width<600)assert(await figure.locator('.ls-room-scroll').evaluate(el=>el.scrollLeft>0),'Seat search did not pan room');
  await figure.locator('input').fill('A1');await figure.getByRole('button',{name:'Locate',exact:true}).click();assert.match(await figure.locator('[role=status]').textContent(),/Accessible/);
  const seat=figure.locator('[data-seat="A1"]');await seat.focus();await page.keyboard.press('ArrowRight');assert.match(await figure.locator('[role=status]').textContent(),/Seat A2/);
  const b3=figure.locator('[data-seat="B3"]');if(width<=1024)await b3.tap();else await b3.click();assert.match(await figure.locator('[role=status]').textContent(),/Seat B3/);
  await figure.locator('input').fill('NOT-A-ROLL');await figure.getByRole('button',{name:'Locate',exact:true}).click();assert.match(await figure.locator('[role=status]').textContent(),/No sample match/);
  await figure.getByRole('button',{name:'Preview sample swap'}).click();assert.match(await figure.locator('[role=status]').textContent(),/No records changed/);
  for(const role of ['teacher','student','admin']){
   await page.locator('[data-workspace-tab="'+role+'"]').click();
   assert(await page.locator('[data-workspace-panel="'+role+'"]').isVisible());
   assert.equal(await page.locator('[data-workspace-panel]:visible').count(),1);
  }
  await page.locator('.ls-faq-list summary').first().focus();await page.keyboard.press('Enter');assert.equal(await page.locator('.ls-faq-list details[open]').count(),1);await page.keyboard.press('Enter');
  const links=await page.locator('a').evaluateAll(nodes=>nodes.filter(n=>n.textContent.toLowerCase().includes('sign in')).every(n=>new URL(n.href).pathname==='/sign-in'));assert(links);
  assert(!await overflow());report.viewports.push(width);pass(width+': all sections, readable local diagrams, sample seat interaction, screenshots, FAQ and sign-in');
  await context.close();
 }
 for(const viewport of [{width:844,height:390},{width:1024,height:768},{width:390,height:844}]){
  const context=await browser.newContext({viewport,hasTouch:true}),page=await context.newPage();page.on('pageerror',e=>report.errors.push(e.message));await page.goto(base);await page.waitForFunction(()=>document.body.dataset.storyInitialized);
  for(const rotated of [viewport,{width:viewport.height,height:viewport.width},viewport]){await page.setViewportSize(rotated);await page.waitForTimeout(300);await page.evaluate(()=>document.getElementById('seating').scrollIntoView({behavior:'instant'}));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));}
  if(viewport.width<1024){await page.locator('.ls-mobile-nav summary').click();const bounds=await page.locator('.ls-mobile-nav nav').boundingBox();assert(bounds.y+bounds.height<=viewport.height+1,'Landscape menu exceeds screen');await page.locator('.ls-mobile-nav a[href="#faq"]').click();assert(!await page.locator('main').evaluate(el=>el.inert));}
  pass(viewport.width+'×'+viewport.height+': landscape/portrait rotation and bounded menu');await context.close();
 }
 for(const options of [{reducedMotion:'reduce'},{javaScriptEnabled:false}]){
  const context=await browser.newContext({viewport:{width:320,height:844},...options}),page=await context.newPage();await page.goto(base);await page.waitForTimeout(200);
  assert(await page.locator('h1').isVisible());assert.equal(await page.locator('.pin-spacer').count(),0);
  await page.locator('.ls-faq-list summary').first().click();assert(await page.locator('.ls-faq-list details[open]').count());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  if(options.reducedMotion){await page.locator('.ls-mobile-nav summary').click();await page.keyboard.press('Escape');assert.equal(await page.locator('.ls-mobile-nav').getAttribute('open'),null);}
  pass(options.reducedMotion?'320px reduced motion: complete content, interactive controls, no pins':'320px JavaScript disabled: static content, native menu and FAQ, no overflow');await context.close();
 }
 assert.deepEqual(report.errors,[]);assert.deepEqual(report.failedAssets,[]);pass('No browser errors, failed assets or public-demo POST requests');
 }finally{fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
