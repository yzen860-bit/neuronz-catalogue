(function(){
"use strict";
const raw = window.NEURONZ_DATA || document.getElementById('nnz-data').textContent;
const DB = typeof raw === 'string' ? JSON.parse(raw) : raw;
const R = DB.records, M = DB.meta;
const ROOT = document.querySelector('.nnz');
const $ = s => ROOT.querySelector(s);
const el = (t,c,x) => { const n=document.createElement(t); if(c)n.className=c; if(x!=null)n.textContent=x; return n; };

/* Search highlighting: wrap each case-insensitive match of the current query in <mark>. */
function hl(text,query){
  const f=document.createDocumentFragment(), s=String(text==null?'':text), q=query===undefined?state.q:query;
  const low=s.toLowerCase();
  if(!q || low.length!==s.length){ f.append(s); return f; }
  let i=0, j;
  while((j=low.indexOf(q,i))!==-1){
    if(j>i) f.append(s.slice(i,j));
    f.append(el('mark','hit',s.slice(j,j+q.length))); i=j+q.length;
  }
  if(i<s.length) f.append(s.slice(i));
  return f;
}
const elh=(t,c,x)=>{ const n=el(t,c); n.append(hl(x)); return n; };

const STATUS_ORDER = ['DEAD','CONTENT_LOST','UNREACHABLE','MOVED','UNVERIFIABLE','OK'];
const STATUS_LABEL = {OK:'Resolves',MOVED:'Moved — redirected',DEAD:'Dead — not found',
  CONTENT_LOST:'Gone — redirected to a home page',UNREACHABLE:'No response from the server',UNVERIFIABLE:'Blocked to automated checks',
  UNKNOWN:'Not checked',NONE:'No link recorded'};

function worst(r){
  if(!r.links.length) return 'NONE';
  for(const s of STATUS_ORDER) if(r.links.some(l=>l.status===s)) return s;
  return 'UNKNOWN';
}
R.forEach(r=>{ r._status = worst(r); r._hay = [r.record_id,r.name,r.summary,r.owner,r.category,
  (r.neuro_terms||[]).join(' '),r.population,r.design_type,r.age_group].filter(Boolean).join(' ').toLowerCase(); });

/* ---------- category colours and icons ---------- */
const CAT = {
  'Administrative':               ['#3B82F6','#2563EB','<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM9 11h6M9 15h6M9 19h3"/>'],
  'International data system':    ['#8B5CF6','#6D28D9','<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18"/>'],
  'National survey':              ['#FF7A59','#E5484D','<path d="M4 20h16M7 16v-5M12 16V6M17 16v-8"/>'],
  'Neurological workforce':       ['#F2508B','#C026D3','<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.2c2.9.4 5 2.7 5 5.8"/>'],
  'Registry':                     ['#22B8E0','#0E7FB0','<ellipse cx="12" cy="5.5" rx="7" ry="2.5"/><path d="M5 5.5v13c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-13M5 12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5"/>'],
  'Research survey/cohort study': ['#F7B733','#E07B12','<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3M7.5 15h9"/>'],
  'Use of NZ neurological data':  ['#34C38F','#0F8F6B','<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z"/>'],
  'Published research':           ['#14B8A6','#0F766E','<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5M8 7h8M8 11h6"/>']
};
/* Categories now follow the Zotero subfolders under "01 Catalogue Sources";
   the workbook's older labels map to the same colours. */
const CAT_ALIAS = {
  'administrative data':'Administrative', 'international data systems':'International data system',
  'national surveys':'National survey', 'neurological workforce data':'Neurological workforce',
  'registries':'Registry', 'research surveys & cohort studies':'Research survey/cohort study',
  'use of nz neurological data':'Use of NZ neurological data', 'published research':'Published research'
};
Object.keys(CAT).forEach(k=>{ CAT_ALIAS[k.toLowerCase()]=k; });
const catKey = c => CAT_ALIAS[String(c||'').toLowerCase()];
const catIcon = c => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+((CAT[catKey(c)]||[])[2]||'<circle cx="12" cy="12" r="6"/>')+'</svg>';
const catVars = (n,c) => { const k=CAT[catKey(c)]||['#5D6E76','#16232A']; n.style.setProperty('--c',k[0]); n.style.setProperty('--c2',k[1]); };

/* ---------- facet option lists ---------- */
const uniq = f => [...new Set(R.map(f).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
function fill(sel, list, anyLabel){
  const s=$(sel); s.appendChild(new Option(anyLabel,''));
  list.forEach(v=>s.appendChild(new Option(v,v)));
}
fill('#f-cat', uniq(r=>r.category), 'All categories');
fill('#f-own', uniq(r=>r.owner), 'All organisations');
fill('#f-neuro', [...new Set(R.flatMap(r=>r.neuro_terms||[]))].sort((a,b)=>a.localeCompare(b)), 'Any focus');
fill('#f-type', uniq(r=>r.type_of_data), 'Any type');
fill('#f-idi', uniq(r=>r.idi), 'Any');
const decades=[...new Set(R.map(r=>r.period_start).filter(Boolean).map(y=>Math.floor(y/10)*10))].sort((a,b)=>b-a);
const ds=$('#f-per'); ds.appendChild(new Option('Any period',''));
decades.forEach(d=>ds.appendChild(new Option(d+'s',String(d))));

const FLAGS=[['ncc','Named conditions (NCC)'],['sfl','Symptoms & function (SFL)'],['nwf','Workforce (NWF)']];
const cw=$('#f-content');
FLAGS.forEach(([k,lab])=>{
  const l=el('label'); const i=el('input'); i.type='checkbox'; i.value=k;
  l.append(i, el('span',null,lab), el('span','ct','')); cw.appendChild(l);
});

/* ---------- state ---------- */
const state={q:'',cat:'',own:'',neuro:'',type:'',idi:'',per:'',flags:new Set(),status:'',sort:'name'};

function matches(r,skip){
  if(state.q && !r._hay.includes(state.q)) return false;
  if(skip!=='cat' && state.cat && r.category!==state.cat) return false;
  if(skip!=='own' && state.own && r.owner!==state.own) return false;
  if(skip!=='neuro' && state.neuro && !(r.neuro_terms||[]).includes(state.neuro)) return false;
  if(skip!=='type' && state.type && r.type_of_data!==state.type) return false;
  if(skip!=='idi' && state.idi && r.idi!==state.idi) return false;
  if(skip!=='per' && state.per){ const d=+state.per;
    if(!r.period_start) return false;
    const end = r.ongoing ? 2026 : (r.period_end||r.period_start);
    if(end < d || r.period_start > d+9) return false; }
  if(skip!=='flags') for(const f of state.flags) if(!r[f]) return false;
  if(skip!=='status' && state.status && r._status!==state.status) return false;
  return true;
}

/* ---------- ledger ---------- */
const counts={}; R.forEach(r=>{counts[r._status]=(counts[r._status]||0)+1});
$('#lcdate').textContent = M.link_check_date;
const bar=$('#bar'), key=$('#key');
['OK','MOVED','UNVERIFIABLE','UNREACHABLE','CONTENT_LOST','DEAD','NONE'].forEach(s=>{
  const n=counts[s]||0; if(!n) return;
  if(s!=='NONE'){
    const b=el('button','seg-'+s); b.style.flex=n; b.type='button';
    b.setAttribute('aria-label',STATUS_LABEL[s]+': '+n+' records');
    b.setAttribute('aria-pressed','false');
    b.addEventListener('click',()=>{ state.status = state.status===s?'':s; render(); });
    b.dataset.s=s; bar.appendChild(b);
  }
  const li=el('li'); const d=el('span','dot d-'+s);
  li.append(d, el('span',null,STATUS_LABEL[s]), el('span','n',String(n))); key.appendChild(li);
});

/* ---------- hero stats and category blocks ---------- */
const terms = new Set(R.flatMap(r=>r.neuro_terms||[]));
[[R.length,'Data sources','linear-gradient(135deg,#FF7A59,#F2508B)'],
 [uniq(r=>r.category).length,'Source types','linear-gradient(135deg,#8B5CF6,#6D28D9)'],
 [uniq(r=>r.owner).length,'Organisations','linear-gradient(135deg,#22B8E0,#0E7FB0)'],
 [terms.size,'Conditions & foci','linear-gradient(135deg,#34C38F,#0F8F6B)']
].forEach(([n,lab,bg])=>{ const li=el('li'); li.style.setProperty('--bg',bg);
  li.append(el('b',null,String(n)), el('span',null,lab)); $('#stats').append(li); });

const catBox=$('#cats');
uniq(r=>r.category).forEach(c=>{
  const b=el('button','cat-tile'); b.type='button'; b.dataset.cat=c; catVars(b,c);
  const ic=el('span','ic'); ic.innerHTML=catIcon(c);
  b.append(ic, el('span','ct',String(R.filter(r=>r.category===c).length)), el('span','nm',c));
  b.setAttribute('aria-pressed','false');
  b.addEventListener('click',()=>{
    state.cat = state.cat===c ? '' : c; $('#f-cat').value=state.cat; render();
    $('.main').scrollIntoView({behavior:'smooth',block:'start'});
  });
  catBox.append(b);
});

/* ---------- render ---------- */
function ageBar(date){
  const w=el('span','age');
  const months = date ? Math.max(0,Math.round((new Date(M.build_date)-new Date(date))/2629800000)) : null;
  for(let i=0;i<6;i++){ const t=el('i'); if(months!=null && i<Math.min(6,months+1)) t.className='f'; w.appendChild(t); }
  return w;
}

/* The heading is the shortest route to the source, so it carries the link.
   A record with no recorded link keeps a plain heading rather than a dead one,
   and a link known to be broken says so before it is clicked. */
function title(r){
  const h=el('h3');
  const primary = r.links && r.links.length ? r.links[0] : null;
  if(!primary){
    h.append(hl(r.name), el('span','nolink','no source link'));
    return h;
  }
  const a=elh('a',null,r.name);
  a.href=primary.url; a.target='_blank'; a.rel='noopener noreferrer';
  a.className='s-'+primary.status;
  const broken = primary.status==='DEAD' || primary.status==='CONTENT_LOST';
  a.title = (broken ? 'This link did not resolve when checked on '+M.link_check_date+'. ' : '')
          + 'Opens '+primary.url+' in a new tab';
  a.setAttribute('aria-label', r.name + (broken ? ' — source link is broken; ' : ' — ')
          + 'opens the source in a new tab');
  const ext=el('span','ext', broken ? '\u2717' : '\u2197');
  ext.setAttribute('aria-hidden','true');
  a.append(ext);
  h.append(a);
  return h;
}

function card(r){
  const a=el('article','rec st-'+r._status); a.id=r.record_id;
  const h=el('div','rec-head'), left=el('div');
  const rid=elh('a','rid',r.record_id); rid.href='#'+r.record_id;
  rid.title='Permanent link to this record';
  if(r.category){ const p=el('span','catpill'); catVars(p,r.category);
    const i=el('i'); i.innerHTML=catIcon(r.category); p.append(i, hl(r.category)); left.append(p); }
  left.append(rid, title(r));
  if(r.summary) left.append(elh('p','sum',r.summary));
  const tags=el('div','tags');
  const add=(t,on)=>{ if(t) tags.append(elh('span','tag'+(on?' on':''),t)); };
  add(r.owner); add(r.type_of_data);
  if(r.ncc) add('NCC',1); if(r.sfl) add('SFL',1); if(r.nwf) add('NWF',1);
  (r.neuro_terms||[]).slice(0,3).forEach(t=>add(t));
  if(r.period) add(r.period);
  if(r.idi) add('IDI: '+r.idi);
  left.append(tags);
  // the record matched, but only on a field the card does not show
  if(state.q && !left.querySelector('mark.hit'))
    left.append(el('p','hitnote','Match found in the full record — open it below'));
  h.append(left);

  const st=el('div','stamp');
  /* Two different claims, kept apart on purpose. `last_verified` is a person
     reading the source and logging it. `link_checked` is a machine confirming
     the URL answered — weaker evidence, and it must not be allowed to pass for
     the stronger kind. */
  st.append(el('span','lbl','Verified against source'));
  st.append(el('span','dt', r.last_verified || 'not recorded'));
  if(r.last_verified) st.append(ageBar(r.last_verified));
  const ls=el('span','lstat');
  ls.append(el('span','dot d-'+r._status), el('span',null,STATUS_LABEL[r._status]));
  st.append(ls);
  if(r.link_checked){
    const lc=el('span','lcheck','link checked '+r.link_checked);
    lc.title='Automated check: the address responded. It does not confirm the content.';
    st.append(lc);
  }
  h.append(st); a.append(h);

  const btn=el('button','more','Full record and evidence'); btn.type='button';
  const det=el('div','detail'); det.hidden=true;
  btn.setAttribute('aria-expanded','false');
  btn.addEventListener('click',()=>{
    const open=det.classList.toggle('open');
    det.hidden=!open; btn.setAttribute('aria-expanded',String(open));
    btn.textContent = open ? 'Hide full record' : 'Full record and evidence';
    if(open && !det.dataset.built){ buildDetail(det,r); det.dataset.built='1'; }
  });
  a.append(btn,det);
  return a;
}

function buildDetail(det,r){
  if(r._status==='DEAD'||r._status==='CONTENT_LOST'){
    det.append(el('p','warn','A source link for this record did not resolve when checked on '+M.link_check_date+'. It needs a replacement or a corrected address.'));
  }
  const dl=el('dl','dl');
  const row=(k,v)=>{ if(v==null||v==='') return; dl.append(el('dt',null,k), elh('dd',null,v)); };
  row('Population', r.population); row('Age group', r.age_group);
  row('Design', r.design_type); row('Approx. size', r.sample_size);
  row('Data period', r.period); row('In the IDI', r.idi);
  row('Access', r.access);
  det.append(dl);

  det.append(el('h4',null,'Source links'));
  const lk=el('ul','lk');
  if(!r.links.length) lk.append(el('li',null,'No source link is recorded for this entry.'));
  r.links.forEach(l=>{
    const li=el('li'); li.append(el('span','dot d-'+l.status));
    const wrap=el('div');
    const a=el('a',null,l.url); a.href=l.url; a.rel='noopener'; a.target='_blank';
    wrap.append(a);
    const meta=el('div','lmeta');
    meta.append(el('span','tag',STATUS_LABEL[l.status]||l.status));
    if(l.checked) meta.append(el('span','tag','checked '+l.checked));
    wrap.append(meta);
    if(l.final_url && l.final_url!==l.url)
      wrap.append(el('div','lnote','now resolves to '+l.final_url));
    else if(l.check_note) wrap.append(el('div','lnote',l.check_note));
    li.append(wrap); lk.append(li);
  });
  det.append(lk);

  if(r.evidence && r.evidence.length){
    det.append(el('h4',null,'Evidence — what was checked, and where'));
    const ul=el('ul','ev');
    r.evidence.forEach(e=>{
      const li=el('li'); li.append(el('code',null,e.evidence_id));
      const mid=el('div');
      mid.append(el('div',null,[e.field,e.evidence_type].filter(Boolean).join(' · ')));
      if(e.url){ const a=el('a',null,e.source_title||e.url); a.href=e.url; a.rel='noopener'; a.target='_blank'; mid.append(a); }
      else if(e.source_title) mid.append(el('div',null,e.source_title));
      li.append(mid, el('span','when', e.access_date||''));
      ul.append(li);
    });
    det.append(ul);
  }
}

function render(){
  const out=R.filter(r=>matches(r));
  out.sort((a,b)=>{
    switch(state.sort){
      case 'id': return a.n-b.n;
      case 'verified': return (a.last_verified||'0').localeCompare(b.last_verified||'0');
      case 'status': return STATUS_ORDER.indexOf(a._status)-STATUS_ORDER.indexOf(b._status) || a.name.localeCompare(b.name);
      default: return a.name.localeCompare(b.name);
    }
  });
  const box=$('#results'); box.textContent='';
  if(!out.length){
    const e=el('div','empty');
    e.append(el('p','','No data source matches these filters.'));
    const b=el('button','reset','Clear all filters'); b.type='button';
    b.addEventListener('click',clearAll); e.append(b); box.append(e);
  } else out.forEach(r=>box.append(card(r)));

  $('#count').innerHTML = '<b>'+out.length+'</b> of '+R.length+' data sources'+
    (state.status?' · link status: '+STATUS_LABEL[state.status].toLowerCase():'');
  if(state.q) $('#count').append(el('span','qnote',' matching “'+$('#q').value.trim()+'”'));

  // live facet counts on the flag checkboxes
  cw.querySelectorAll('label').forEach(l=>{
    const k=l.querySelector('input').value;
    const n=R.filter(r=>r[k] && matches(r,'flags')).length;
    l.querySelector('.ct').textContent=n;
  });
  bar.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.s===state.status)));
  catBox.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.cat===state.cat)));

  if(location.hash){ const t=document.getElementById(location.hash.slice(1)); if(t) t.scrollIntoView({block:'center'}); }
}

/* ---------- wiring ---------- */
const bind=(sel,key)=>$(sel).addEventListener('change',e=>{state[key]=e.target.value;render()});
bind('#f-cat','cat'); bind('#f-own','own'); bind('#f-neuro','neuro');
bind('#f-type','type'); bind('#f-idi','idi'); bind('#f-per','per'); bind('#sort','sort');
let t; $('#q').addEventListener('input',e=>{clearTimeout(t);t=setTimeout(()=>{state.q=e.target.value.trim().toLowerCase();render()},140)});
cw.addEventListener('change',e=>{ if(e.target.checked) state.flags.add(e.target.value); else state.flags.delete(e.target.value); render(); });
function clearAll(){
  Object.assign(state,{q:'',cat:'',own:'',neuro:'',type:'',idi:'',per:'',status:''});
  state.flags.clear();
  $('#q').value=''; ['#f-cat','#f-own','#f-neuro','#f-type','#f-idi','#f-per'].forEach(s=>$(s).value='');
  cw.querySelectorAll('input').forEach(i=>i.checked=false);
  render();
}
$('#reset').addEventListener('click',clearAll);
window.addEventListener('hashchange',()=>{const t=document.getElementById(location.hash.slice(1)); if(t) t.scrollIntoView({block:'center'})});

$('#prov').textContent='Compiled from '+M.source_workbook+', published '+M.catalogue_published+
  ' and updated '+M.catalogue_updated+' by '+M.compiled_by+'. Link status from an automated check on '+
  M.link_check_date+' with redirects adjudicated by hand. Verification dates come from the workbook\u2019s evidence log; '+
  R.filter(r=>!r.last_verified).length+' of '+R.length+' entries have no evidence entry recorded against them.';
$('#src').textContent='Workbook MD5 '+M.source_md5+' · page built '+M.build_date;

/* ---------- research publications ----------
   Literature from the Zotero "Journal Articles" folder. Kept apart from the
   register above: these are papers that use or report NZ neurological data,
   not data sources, so link status and IDI filters do not apply to them. */
const P = DB.publications || [];
const pstate = {q:'', cond:'', sort:'new'};
function initPubs(){
  const sec=$('#nnz-pubs'); if(!sec || !P.length) return;
  sec.hidden=false;
  const jump=$('#pubjump');
  if(jump){ jump.hidden=false; jump.textContent='Also: '+P.length+' research publications ↓'; }
  P.forEach(p=>{ p._hay=[p.id,p.title,(p.authors||[]).join(' '),p.journal,p.journal_abbr,
    (p.conditions||[]).join(' '),p.doi,p.pmid,p.year].filter(Boolean).join(' ').toLowerCase(); });
  const names=[...new Set(P.flatMap(p=>p.conditions||[]))].sort((a,b)=>a.localeCompare(b));
  const cs=$('#p-cond'); cs.appendChild(new Option('All conditions',''));
  names.forEach(n=>cs.appendChild(new Option(n+' ('+P.filter(p=>(p.conditions||[]).includes(n)).length+')',n)));
  cs.addEventListener('change',e=>{pstate.cond=e.target.value;renderPubs()});
  $('#p-sort').addEventListener('change',e=>{pstate.sort=e.target.value;renderPubs()});
  let pt; $('#pq').addEventListener('input',e=>{clearTimeout(pt);pt=setTimeout(()=>{pstate.q=e.target.value.trim().toLowerCase();renderPubs()},140)});
  renderPubs();
}
function fmtDate(d){
  if(!d) return 'Date not recorded';
  const m=String(d).match(/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?/); if(!m) return d;
  const mon=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return (m[3]?(+m[3])+' ':'')+(m[2]?mon[+m[2]-1]+' ':'')+m[1];
}
function pubItem(p){
  const q=pstate.q, li=el('li','pub'); li.id='pub-'+p.id;
  const meta=el('div','pub-meta');
  const pid=el('a','rid'); pid.href='#pub-'+p.id; pid.append(hl(p.id,q)); pid.title='Permanent link to this publication';
  meta.append(pid, el('span','pdate',fmtDate(p.date)));
  if(p.journal){ const j=el('span','pjour'); j.append(hl(p.journal_abbr||p.journal,q)); j.title=p.journal; meta.append(j); }
  li.append(meta);

  const h=el('h3'); const href=p.doi?'https://doi.org/'+p.doi:(p.url||'');
  if(href){ const a=el('a'); a.href=href; a.target='_blank'; a.rel='noopener noreferrer';
    a.append(hl(p.title,q)); const x=el('span','ext','↗'); x.setAttribute('aria-hidden','true'); a.append(x);
    a.setAttribute('aria-label',p.title+' — opens the article in a new tab'); h.append(a); }
  else h.append(hl(p.title,q));
  li.append(h);

  const au=p.authors||[];
  if(au.length){ const s=au.length>3?au.slice(0,3).join(', ')+' et al.':au.join(', ');
    const pa=el('p','auth'); pa.append(hl(s,q)); if(au.length>3) pa.title=au.join(', '); li.append(pa); }

  const tags=el('div','tags');
  (p.conditions||[]).forEach(c=>{ const t=el('span','tag on'); t.append(hl(c,q)); tags.append(t); });
  if(p.doi){ const a=el('a','tag idlink','DOI'); a.href='https://doi.org/'+p.doi; a.target='_blank'; a.rel='noopener noreferrer'; a.title='doi.org/'+p.doi; tags.append(a); }
  if(p.pmid){ const a=el('a','tag idlink','PubMed '+p.pmid); a.href='https://pubmed.ncbi.nlm.nih.gov/'+p.pmid+'/'; a.target='_blank'; a.rel='noopener noreferrer'; tags.append(a); }
  li.append(tags);

  if(p.abstract){
    const btn=el('button','more','Abstract'); btn.type='button'; btn.setAttribute('aria-expanded','false');
    const det=el('div','detail'); det.hidden=true;
    const ab=el('p','abs'); ab.append(hl(p.abstract,q)); det.append(ab);
    btn.addEventListener('click',()=>{ const o=det.classList.toggle('open'); det.hidden=!o;
      btn.setAttribute('aria-expanded',String(o)); btn.textContent=o?'Hide abstract':'Abstract'; });
    li.append(btn,det);
  }
  return li;
}
function renderPubs(){
  const out=P.filter(p=>(!pstate.q||p._hay.includes(pstate.q)) && (!pstate.cond||(p.conditions||[]).includes(pstate.cond)));
  out.sort((a,b)=> pstate.sort==='title' ? a.title.localeCompare(b.title)
    : pstate.sort==='old' ? String(a.date||'9999').localeCompare(String(b.date||'9999'))
    : String(b.date||'').localeCompare(String(a.date||'')));
  const list=$('#publist'); list.textContent='';
  if(!out.length){ list.append(el('li','empty','No publication matches this search.')); }
  else out.forEach(p=>list.append(pubItem(p)));
  $('#pcount').innerHTML='<b>'+out.length+'</b> of '+P.length+' publications';
}
initPubs();

render();
})();
