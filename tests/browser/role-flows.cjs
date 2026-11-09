const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
const base=process.env.BASE_URL||'http://127.0.0.1:8001',out=process.env.QA_OUT||'docs/role-flows';fs.mkdirSync(out,{recursive:true});
(async()=>{const b=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'}),report={pages:[],errors:[]};try{
for(const role of ['teacher','student']){
 const c=await b.newContext(),p=await c.newPage();p.on('pageerror',e=>report.errors.push(e.message));
 await p.goto(base+'/sign-in');await p.locator('[name=email]').fill(role+'@gmail.com');await p.locator('[name=password]').fill(process.env.DEMO_PASSWORD||'12345678');await Promise.all([p.waitForURL('**/'+role+'/'+role+'-dashboard'),p.locator('[data-auth-form] button[type=submit]').click()]);
 const routes=role==='teacher'?['/teacher/courses','/teacher/courses?tab=marks','/teacher/courses?tab=submissions','/teacher/courses?tab=students','/teacher/courses?tab=import','/teacher/schedule','/teacher/duties','/teacher/profile']:['/student/exams','/student/exams?tab=admit','/student/exams?tab=seat','/student/results','/student/rechecking','/student/profile'];
 for(const route of routes)for(const width of [375,768,1440]){
  await p.setViewportSize({width,height:1000});const r=await p.goto(base+route);assert.equal(r.status(),200,route);
  await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(100);assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow '+route+' '+width);
  await p.screenshot({path:out+'/'+role+'-'+routes.indexOf(route)+'-'+width+'.png',fullPage:true});report.pages.push({route,width});
 }
 if(role==='teacher'){
  await p.goto(base+'/teacher/profile');assert(await p.getByText('My profile',{exact:true}).isVisible());assert(await p.locator('form[action="/workflow/availability"]').isVisible());assert.equal(await p.getByText('My invigilation duties',{exact:true}).count(),0);
  await p.goto(base+'/teacher/duties');const request=p.locator('details').filter({has:p.getByText('Cannot attend? Request cover',{exact:true})}).first();if(await request.count()){assert(!await request.locator('textarea').isVisible());await request.locator('summary').click();assert(await request.locator('textarea').isVisible());}
 }else{
  await p.goto(base+'/student/results');const semesters=p.locator('[name=semester_id]');if(await semesters.locator('option').count()>1){const value=await semesters.locator('option').last().getAttribute('value');await semesters.selectOption(value);await Promise.all([p.waitForURL(u=>u.searchParams.get('semester_id')===value),p.getByRole('button',{name:'Show results',exact:true}).click()]);assert.equal(new URL(p.url()).searchParams.get('semester_id'),value);}
  const review=p.locator('details').filter({has:p.getByText('Request recheck',{exact:true})}).first();if(await review.count()){assert(!await review.locator('textarea').isVisible());await review.locator('summary').click();await review.locator('textarea').fill('Please review the component total for this course.');await review.getByRole('button',{name:'Submit rechecking request'}).click();await p.locator('#confirm-dialog').waitFor({state:'visible'});await p.locator('#confirm-dialog').getByRole('button',{name:'Cancel',exact:true}).click();assert(!await p.locator('#confirm-dialog').isVisible());}
  await p.goto(base+'/student/profile');assert(await p.getByText('My courses',{exact:true}).isVisible());
 }
 await c.close();
}
assert.deepEqual(report.errors,[]);console.log(report.pages.length+' role-task/viewport checks passed; semester filtering, optional request forms, profile/availability and cancelled recheck confirmation passed. No academic forms submitted.');
}finally{fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await b.close()}})().catch(e=>{console.error(e);process.exit(1)});
