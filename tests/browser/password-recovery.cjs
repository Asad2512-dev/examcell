// Render actual recovery Blade states without creating users, sending mail or changing passwords.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const base=process.env.BASE_URL||'http://127.0.0.1:8001';
const php=process.env.PHP_BINARY||'/Applications/MAMP/bin/php/php8.3.14/bin/php';
const render=mode=>execFileSync(php,['-r',`
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
config(['app.url'=>${JSON.stringify(base)}]);
Illuminate\\Support\\Facades\\URL::forceRootUrl(${JSON.stringify(base)});
echo view('backend.auth',['errors'=>new Illuminate\\Support\\ViewErrorBag(),'mode'=>${JSON.stringify(mode)},'token'=>str_repeat('a',64),'email'=>'sample@example.test','recoveryEmail'=>'sample@example.test','resendAfter'=>2])->render();
`],{encoding:'utf8'});
(async()=>{
 const html=Object.fromEntries(['reset','sent','reset-invalid'].map(mode=>[mode,render(mode)]));
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/__recovery-preview/*',route=>route.fulfill({status:200,contentType:'text/html',body:html[new URL(route.request().url()).pathname.split('/').pop()]}));
  for(const width of [375,768,1280]){
   await page.setViewportSize({width,height:1000});
   for(const mode of Object.keys(html)){
    await page.goto(base+'/__recovery-preview/'+mode);await page.evaluate(()=>document.fonts.ready);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),mode+' overflow');
   }
  }
  await page.goto(base+'/__recovery-preview/reset');
  await page.locator('[name=password]').fill('LongPrivatePassword2026!');
  await page.locator('[name=password_confirmation]').fill('WrongPrivatePassword2026!');
  assert(!await page.locator('[name=password_confirmation]').evaluate(el=>el.checkValidity()));
  await page.locator('[name=password_confirmation]').fill('LongPrivatePassword2026!');
  assert(await page.locator('[name=password_confirmation]').evaluate(el=>el.checkValidity()));
  assert.equal(await page.locator('[data-recovery-match]').getAttribute('data-valid'),'true');
  assert.equal(await page.locator('[data-recovery-length]').getAttribute('data-valid'),'true');
  await page.goto(base+'/__recovery-preview/sent');
  assert(await page.locator('[data-resend-button]').isDisabled());
  await page.waitForFunction(()=>!document.querySelector('[data-resend-button]').disabled);
  assert.equal(await page.getByRole('link',{name:'Use a different email address'}).count(),1);
  await page.goto(base+'/__recovery-preview/reset-invalid');
  assert.equal(await page.locator('[name=password]').count(),0);
  assert.deepEqual(errors,[]);
  console.log('Recovery Blade states: 3 widths, password matching, resend cooldown and invalid-link path passed; no account changes or emails.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
