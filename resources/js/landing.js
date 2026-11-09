import { uiText } from './core/content.js';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/** One page-local context. matchMedia owns and reverts all timelines on breakpoint changes. */
function initLandingStory() {
  const root = document.querySelector('.landing-story');
  if (!root || root.dataset.storyInitialized) return;
  root.dataset.storyInitialized = 'true';
  const select = gsap.utils.selector(root);
  const media = gsap.matchMedia();
  const cleanup = [];
  const listen = (target, type, handler) => {
    target.addEventListener(type, handler);
    cleanup.push(() => target.removeEventListener(type, handler));
  };
  cleanup.push(initSectionNavigation(root));
  cleanup.push(initMobileNavigation(root, listen));
  initCleanSectionLinks(root, listen);
  initSeatingDemo(root, listen);
  cleanup.push(initWorkspaceTabs(root, listen));
  let refreshFrame, refreshTimer;
  const refresh = () => {
    cancelAnimationFrame(refreshFrame);
    clearTimeout(refreshTimer);
    // Lazy screenshots can finish loading during a long native anchor scroll.
    // Wait for scrolling to settle before recalculating pin geometry.
    if (ScrollTrigger.isScrolling()) {
      refreshTimer = setTimeout(refresh, 150);
      return;
    }
    refreshFrame = requestAnimationFrame(() => ScrollTrigger.refresh());
  };

  root.querySelectorAll('details').forEach(details => {
    listen(details, 'toggle', () => {
      if (details.open && details.matches('.ls-faq-list details') && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        details.querySelector('div')?.animate([{opacity:.4,transform:'translateY(-4px)'},{opacity:1,transform:'translateY(0)'}], {duration:180,easing:'ease-out'});
      }
      // The mobile menu is absolutely positioned. Refreshing pins while it closes
      // interrupts the browser's native smooth scroll to the selected fragment.
      if (!details.matches('.ls-mobile-nav')) refresh();
    });
  });
  root.querySelectorAll('img').forEach(img => { if (!img.complete) listen(img, 'load', refresh); });

  media.add({ wide:'(min-width: 1200px) and (min-height: 700px)', narrow:'(max-width: 1199px), (max-height: 699px)', reduce:'(prefers-reduced-motion: reduce)' }, context => {
    const { wide, reduce } = context.conditions;
    if (reduce) { root.dataset.motion = 'reduced'; return; }
    root.dataset.motion = 'enabled';
    root.classList.toggle('ls-motion-wide', wide);
    const hero = gsap.timeline({defaults:{ease:'power3.out'}});
    hero.from(select('.ls-hero-label, .ls-art-coordinate, .ls-cross'), {opacity:0,duration:.6,stagger:.06},0)
      .from(select('.ls-line > span'), {yPercent:105,duration:.85,stagger:.09},.05)
      .from(select('.ls-hero-description, .ls-hero-footnote'), {opacity:0,y:12,duration:.65,stagger:.1},.3)
      .from(select('.ls-hero-copy .ls-actions'), {y:8,duration:.5},.2)
      .from(select('.ls-hero-calendar'), {opacity:0,y:30,rotation:0,scale:.96,duration:1},.15)
      .from(select('.ls-hero-room'), {opacity:0,y:45,rotation:0,scale:.94,duration:1},.3)
      .from(select('.ls-hero-room .ls-seat'), {opacity:0,duration:.4,stagger:.012},.55)
      .from(select('.ls-hero-validation'), {opacity:0,y:12,duration:.6},.8)
      .fromTo(select('.ls-scan-line'), {y:0,opacity:0}, {y:90,opacity:.7,duration:1.4,ease:'power1.inOut'},.5)
      .to(select('.ls-scan-line'), {opacity:0,duration:.3},1.7);

    // Section entrances are short and reversible; content exists before animation initialization.
    select('[data-reveal]').forEach(element => gsap.from(element, {y:wide?28:12,opacity:.35,duration:.65,ease:'power2.out',scrollTrigger:{trigger:element,start:'top 94%',toggleActions:'play none none reverse'}}));
    if (wide) {
      gsap.to(select('.ls-hero-art'), {y:35,scale:.96,ease:'none',scrollTrigger:{trigger:'.ls-hero',start:'top top',end:'bottom top',scrub:1}});

    }
    if (!wide) gsap.from(select('.ls-hero-mobile'),{opacity:.7,y:10,duration:.65,ease:'power2.out'});
    compactStory(select);
    seatingStory(select, wide);
    drawPath(select('.ls-cta-line path')[0], {trigger:'.ls-final-cta',start:'top 85%',end:'bottom bottom',scrub:1});
    return () => {
      root.classList.remove('ls-motion-wide');
      select('[data-stage]').forEach(step => step.classList.remove('is-active'));
    };
  });

  document.fonts?.ready.then(refresh);
  listen(window, 'load', refresh);
  listen(window, 'pageshow', event => { if (event.persisted) refresh(); });
  // Old shared section links still reach their section, then use the clean landing URL.
  refresh();
  if (location.hash) {
    const fragment=location.hash.slice(1);
    history.replaceState(history.state,'',location.pathname+location.search);
    try {
      const legacy = {planning:'capabilities',results:'capabilities',reports:'capabilities',accountability:'capabilities'};
      const id = decodeURIComponent(fragment);
      const target = document.getElementById(legacy[id] || id);
      requestAnimationFrame(() => target?.scrollIntoView({behavior:'instant',block:'start'}));
    } catch { /* A malformed fragment must not stop the page from working. */ }
  }
  listen(window, 'pagehide', event => {
    if (event.persisted) return;
    media.revert(); cleanup.forEach(fn => fn()); cancelAnimationFrame(refreshFrame); clearTimeout(refreshTimer);
  });
}

function initCleanSectionLinks(root, listen) {
  listen(root,'click',event=>{
    if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    const link=event.target.closest('a[href^="#"]');
    if(!link||link.hasAttribute('download')||link.target==='_blank')return;
    let target;
    try{target=document.getElementById(decodeURIComponent(link.hash.slice(1)));}catch{return;}
    if(!target)return;
    event.preventDefault();
    // Let the mobile menu unlock the page before scrolling. Native links remain
    // in the HTML for keyboard semantics and the JavaScript-disabled fallback.
    requestAnimationFrame(()=>{
      const focusTarget=target.matches('main')?target:target.querySelector('h1,h2')||target;
      if(!focusTarget.hasAttribute('tabindex'))focusTarget.setAttribute('tabindex','-1');
      focusTarget.focus({preventScroll:true});
      target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'});
      if(location.hash)history.replaceState(history.state,'',location.pathname+location.search);
    });
  });
}

function initMobileNavigation(root, listen) {
  const menu=root.querySelector('.ls-mobile-nav'), summary=menu.querySelector('summary');
  const mobile=matchMedia('(max-width:1023px)');
  const background=[root.querySelector('main'),root.querySelector('footer')];
  const originalInert=background.map(element=>element.inert);
  const close=(focus=false)=>{menu.open=false;if(focus)summary.focus({preventScroll:true});};
  const sync=()=>{
    const open=menu.open&&mobile.matches;
    document.documentElement.classList.toggle('ls-menu-open',open);
    summary.setAttribute('aria-expanded',String(open));
    summary.setAttribute('aria-label',open?uiText('landing.runtime.close_navigation'):uiText('landing.runtime.open_navigation'));
    background.forEach((element,i)=>element.inert=open||originalInert[i]);
  };
  listen(menu,'toggle',()=>{sync();if(menu.open&&!matchMedia('(prefers-reduced-motion: reduce)').matches)menu.querySelector('nav').animate([{opacity:.4,transform:'translateY(-5px)'},{opacity:1,transform:'translateY(0)'}],{duration:160,easing:'ease-out'});});
  menu.querySelectorAll('a').forEach(link=>listen(link,'click',()=>{close(true);sync();}));
  listen(document,'keydown',event=>{
    if(!menu.open||!mobile.matches)return;
    if(event.key==='Escape'){event.preventDefault();close(true);sync();}
    if(event.key==='Tab'){
      const controls=[...root.querySelectorAll('.ls-header a,.ls-header summary')].filter(element=>element.checkVisibility());
      const first=controls[0],last=controls.at(-1);
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    }
  });
  listen(document,'pointerdown',event=>{if(menu.open&&!event.target.closest('.ls-header')){close(true);sync();}});
  listen(mobile,'change',()=>{if(!mobile.matches)close();sync();});
  sync();
  return ()=>{document.documentElement.classList.remove('ls-menu-open');background.forEach((element,i)=>element.inert=originalInert[i]);};
}

/** Public demonstration only: synthetic seat IDs, no requests or live student records. */
function initSeatingDemo(root, listen) {
  const figure=root.querySelector('.ls-room-figure'),form=figure.querySelector('[data-demo-search]');
  const svg=figure.querySelector('.ls-room-svg'),seats=[...svg.querySelectorAll('.ls-seat')];
  const status=figure.querySelector('.ls-demo-seat-status'),viewport=figure.querySelector('.ls-room-scroll');
  const swap=figure.querySelector('.ls-demo-swap');
  const descriptions=seats.map((seat,i)=>({seat,label:seat.dataset.seat,roll:'DEMO-'+String(i+1).padStart(3,'0'),group:['CS · A','IT · B','SE · C'][(Math.floor(i/6)+i%6)%3]}));
  figure.classList.add('ls-demo-interactive');form.hidden=false;swap.hidden=false;svg.setAttribute('role','group');
  const show=(item,focus=false)=>{
    seats.forEach(seat=>{seat.classList.toggle('is-selected',seat===item.seat);seat.classList.remove('is-swap-preview');seat.tabIndex=seat===item.seat?0:-1;});
    const note=item.label==='D6'?uiText('landing.runtime.unavailable_seat'):item.label==='A1'?uiText('landing.runtime.accessible_seat'):item.group+'.';
    status.textContent=uiText('landing.runtime.seat_status','Sample :Roll · Seat :Seat · :Note No live student records.',{Roll:item.roll,Seat:item.label,Note:note});
    if(focus)item.seat.focus({preventScroll:true});
    const rect=item.seat.getBoundingClientRect(),frame=viewport.getBoundingClientRect();
    if(rect.left<frame.left||rect.right>frame.right)viewport.scrollTo({left:viewport.scrollLeft+rect.left-frame.left-viewport.clientWidth/2+rect.width/2,behavior:'instant'});
  };
  descriptions.forEach((item,i)=>{
    item.seat.setAttribute('role','button');item.seat.tabIndex=-1;
    item.seat.setAttribute('aria-label',uiText('landing.runtime.seat_label','Sample seat :Seat: :Status',{Seat:item.label,Status:item.label==='D6'?'unavailable':item.label==='A1'?'accessible':item.group}));
    listen(item.seat,'click',()=>show(item));
    listen(item.seat,'keydown',event=>{
      if(['Enter',' '].includes(event.key)){event.preventDefault();show(item);}
      const offset={ArrowRight:1,ArrowLeft:-1,ArrowDown:6,ArrowUp:-6}[event.key];
      if(offset){event.preventDefault();show(descriptions[Math.max(0,Math.min(seats.length-1,i+offset))],true);}
    });
  });
  listen(form,'submit',event=>{
    event.preventDefault();const value=form.querySelector('input').value.trim().toUpperCase();
    const item=descriptions.find(item=>item.label===value||item.roll===value);
    if(item)show(item);
    else status.textContent=uiText('landing.runtime.no_match');
  });
  listen(swap,'click',()=>{
    seats.forEach(seat=>seat.classList.toggle('is-swap-preview',['C2','C4'].includes(seat.dataset.seat)));
    status.textContent=uiText('landing.runtime.swap_proposal');
  });
  show(descriptions[8]);
}

/** Native scroll tracking also works when motion is reduced or GSAP contexts are reverted. */
function initSectionNavigation(root) {
  const header = root.querySelector('.ls-header');
  const links = [...header.querySelectorAll('nav a[href^="#"]')];
  // Later product chapters belong to Capabilities rather than extending Workspaces to the FAQ.
  const chapters = {planning:'capabilities',results:'capabilities',reports:'capabilities',accountability:'capabilities'};
  const targets = new Set(links.map(link => link.hash.slice(1)));
  const sections = [...root.querySelectorAll('main > section[id]')]
    .map(element => ({element,target:chapters[element.id] || element.id}))
    .filter(section => targets.has(section.target));
  let frame = 0, current;
  const update = () => {
    frame = 0;
    const readingLine = header.getBoundingClientRect().bottom + Math.min(innerHeight * .18, 150);
    let active = sections[0]?.target;
    for (const section of sections) {
      if (section.element.getBoundingClientRect().top <= readingLine) active = section.target;
    }
    if (active === current) return;
    current = active;
    for (const link of links) {
      if (link.hash === '#'+active) link.setAttribute('aria-current','location');
      else link.removeAttribute('aria-current');
    }
  };
  const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };
  window.addEventListener('scroll',schedule,{passive:true});
  window.addEventListener('resize',schedule,{passive:true});
  window.addEventListener('hashchange',schedule);
  window.addEventListener('pageshow',schedule);
  const observer = new ResizeObserver(schedule);
  observer.observe(root.querySelector('main'));
  update();
  return () => {
    cancelAnimationFrame(frame); observer.disconnect();
    window.removeEventListener('scroll',schedule);
    window.removeEventListener('resize',schedule);
    window.removeEventListener('hashchange',schedule);
    window.removeEventListener('pageshow',schedule);
  };
}

function drawPath(path, scrollTrigger) {
  if (!path) return;
  const length = path.getTotalLength();
  gsap.fromTo(path, {strokeDasharray:length,strokeDashoffset:length}, {strokeDashoffset:0,ease:'none',scrollTrigger});
}

function compactStory(select) {
  gsap.from(select('[data-feature-step]'), {opacity:.45,y:14,duration:.5,stagger:.07,ease:'power2.out',scrollTrigger:{trigger:'.ls-feature-steps',start:'top 92%',toggleActions:'play none none reverse'}});
  gsap.from(select('.ls-compact-ai-flow span'), {opacity:.3,y:8,duration:.4,stagger:.12,scrollTrigger:{trigger:'.ls-compact-ai',start:'top 92%',toggleActions:'play none none reverse'}});
}

function seatingStory(select, wide) {
  const figure = select('.ls-room-figure')[0];
  const scoped = gsap.utils.selector(figure);
  // Draw and populate the existing room plan; leave the interactive seats in place.
  const wall = scoped('.ls-room-outline path')[0];
  const timeline = gsap.timeline({scrollTrigger:{trigger:figure,start:'top 86%',toggleActions:'play none none reverse'}});
  const length = wall.getTotalLength();
  timeline.fromTo(wall,{strokeDasharray:length,strokeDashoffset:length},{strokeDashoffset:0,duration:.8,ease:'power2.out'})
    .from(scoped('.ls-seat'),{opacity:.2,scale:.92,transformOrigin:'center',stagger:wide?.018:.006,duration:.45},.15);
}

function initWorkspaceTabs(root, listen) {
  const tablist=root.querySelector('.ls-workspace-tabs');
  const buttons=[...root.querySelectorAll('[data-workspace-tab]')];
  const panels=[...root.querySelectorAll('[data-workspace-panel]')];
  if(!tablist||!buttons.length)return ()=>{};
  tablist.hidden=false;
  const activate=(index,focus=false,animate=true)=>{
    buttons.forEach((button,i)=>{button.setAttribute('aria-selected',String(i===index));button.tabIndex=i===index?0:-1;});
    panels.forEach((panel,i)=>{
      gsap.killTweensOf(panel);gsap.set(panel,{clearProps:'opacity,transform'});
      panel.hidden=i!==index;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',buttons[i].id);panel.tabIndex=0;
    });
    if(focus)buttons[index].focus();
    if(animate&&!matchMedia('(prefers-reduced-motion: reduce)').matches)gsap.fromTo(panels[index],{opacity:.6,y:8},{opacity:1,y:0,duration:.3,clearProps:'opacity,transform'});
  };
  buttons.forEach((button,index)=>{
    listen(button,'click',()=>activate(index));
    listen(button,'keydown',event=>{
      const next=event.key==='ArrowRight'?(index+1)%buttons.length:event.key==='ArrowLeft'?(index-1+buttons.length)%buttons.length:event.key==='Home'?0:event.key==='End'?buttons.length-1:null;
      if(next!==null){event.preventDefault();activate(next,true);}
    });
  });
  activate(0,false,false);
  return ()=>panels.forEach(panel=>gsap.killTweensOf(panel));
}

if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initLandingStory,{once:true});
else initLandingStory();
