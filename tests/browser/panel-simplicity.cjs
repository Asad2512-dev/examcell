// Read-only audit of all three role workspaces. Never submit academic forms.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict'), fs = require('node:fs');
const base = process.env.BASE_URL || 'http://127.0.0.1:8001';
const out = process.env.QA_OUT || 'docs/panel-simplicity';
fs.mkdirSync(out,{recursive:true});
const definitions=JSON.parse(execFileSync(process.env.PHP_BINARY||'/Applications/MAMP/bin/php/php8.3.14/bin/php',['-r',"require 'vendor/autoload.php'; echo json_encode(array_combine(['admin','teacher','student'],array_map(fn($r)=>App\\Support\\Workspaces::definition($r),['admin','teacher','student'])));"],{encoding:'utf8'}));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const report={pages:[],checks:[],errors:[]};
 try{
  for(const role of ['admin','teacher','student']){
   const context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage();
   page.on('pageerror',error=>report.errors.push(role+': '+error.message));
   await page.goto(base+'/sign-in');await page.locator('[name=email]').fill(role+'@gmail.com');await page.locator('[name=password]').fill(process.env.DEMO_PASSWORD||'12345678');
   await Promise.all([page.waitForURL('**/'+role+'/'+role+'-dashboard'),page.locator('[data-auth-form] button[type=submit]').click()]);
   assert.equal(await page.locator('.workspace-actions a').count(),3);
   assert(!await page.getByText('Course performance',{exact:true}).isVisible());
   await page.screenshot({path:out+'/'+role+'-desktop.png',fullPage:true});
   for(const link of await page.locator('.workspace-actions a').evaluateAll(links=>links.map(a=>a.href))){
    const response=await context.request.get(link);assert.equal(response.status(),200,'Task link '+link);
   }
   const details=page.locator('.workspace-disclosure').filter({has:page.getByText('Performance and activity',{exact:true})});
   await details.locator(':scope > summary').click();assert(await page.getByText('Course performance',{exact:true}).isVisible());await details.locator(':scope > summary').click();
   if(role==='teacher')assert(await page.locator('.deadline-task').count()<=3);
   for(const workspace of definitions[role]){
    for(const tab of workspace.tabs){
     const url='/'+role+'/'+workspace.slug+(workspace.tabs.length>1?'?tab='+tab.key:'');
     const response=await page.goto(base+url);assert.equal(response.status(),200,url);
     await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(60);
     assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Desktop overflow '+url);
     assert.equal(await page.locator('.sidebar [aria-current=page]').count(),1,'Active navigation '+url);
     report.pages.push({role,url,width:1440,status:response.status()});
    }
   }
   if(role==='teacher'){
    await page.goto(base+'/teacher/courses?tab=marks');
    const select=page.locator('select[name=course_id]');
    if(await select.count()&&await select.locator('option').count()){
     const course=await select.locator('option').last().getAttribute('value');
     await select.selectOption(course);await Promise.all([page.waitForURL(u=>u.searchParams.get('course_id')===course&&u.searchParams.get('tab')==='marks'),page.getByRole('button',{name:'Open course',exact:true}).click()]);
     assert((await page.locator('.tabs a').evaluateAll(links=>links.map(a=>new URL(a.href).searchParams.get('course_id')))).every(value=>value===course),'Course context persists between tabs');
    }
   }
   if(role==='admin'){
    await page.goto(base+'/admin/exams');
    const exam=page.locator('a[href*="/admin/exams?exam="]').first();
    if(await exam.count()){
     await exam.click();const examId=new URL(page.url()).searchParams.get('exam');
     assert((await page.locator('.tabs a').evaluateAll(links=>links.map(a=>new URL(a.href).searchParams.get('exam')))).every(value=>value===examId),'Exam context persists between tabs');
    }
   }
   for(const workspace of definitions[role]){
    await page.setViewportSize({width:375,height:844});
    const url='/'+role+'/'+workspace.slug;const response=await page.goto(base+url);assert.equal(response.status(),200,url);await page.waitForTimeout(100);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile overflow '+url);
    report.pages.push({role,url,width:375,status:response.status()});
   }
   for(const width of [375,768]){
    await page.setViewportSize({width,height:1000});await page.goto(base+'/'+role+'/'+role+'-dashboard');
    await page.locator('[data-open-nav]').click();assert(await page.locator('.sidebar').evaluate(el=>!el.inert));assert(await page.locator('.app-main').evaluate(el=>el.inert));
    await page.getByRole('button',{name:'Close navigation',exact:true}).click();assert(!await page.locator('.app-main').evaluate(el=>el.inert));
    await page.locator('[data-open-nav]').click();await page.keyboard.press('Escape');assert(!await page.locator('.app-main').evaluate(el=>el.inert));
    await page.waitForFunction(()=>document.querySelector('.sidebar').getBoundingClientRect().right<=1);
    await page.screenshot({path:out+'/'+role+'-'+width+'.png',fullPage:true});
   }
   await page.setViewportSize({width:1440,height:1000});await page.evaluate(()=>{document.documentElement.classList.add('dark');window.dispatchEvent(new Event('theme-changed'))});await page.screenshot({path:out+'/'+role+'-dark.png',fullPage:true});
   // Mobile tab strip keeps its active section in view.
   const tabPage=definitions[role].find(w=>w.tabs.length>3)||definitions[role].find(w=>w.tabs.length>1);
   if(tabPage){await page.setViewportSize({width:375,height:844});await page.goto(base+'/'+role+'/'+tabPage.slug+'?tab='+tabPage.tabs.at(-1).key);await page.waitForTimeout(200);assert(await page.locator('.tabs [aria-current=page]').evaluate(el=>{const r=el.getBoundingClientRect(),p=el.parentElement.getBoundingClientRect();return r.left>=p.left-1&&r.right<=p.right+1}),'Active tab visible '+role);}
   report.checks.push(role+': tasks, all workspace sections, mobile navigation, active tabs and dark appearance');
   await context.close();
  }
  assert.deepEqual(report.errors,[]);console.log(report.pages.length+' workspace/viewport checks passed; all three panels passed common task links, disclosures, mobile navigation and dark-mode review.');
 }finally{fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
