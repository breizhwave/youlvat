<?php
// Page publique : les bénévoles s'inscrivent eux-mêmes sur un créneau (prénom, nom, téléphone et e-mail, sans compte).
require_once __DIR__ . '/lib/auth.php';
start_session();
$setupError = null;
try { db(); } catch (Throwable $e) { $setupError = $e instanceof DbSetupError ? $e->getMessage() : 'Connexion à la base impossible.'; }
$boot = ['csrf' => csrf_token(), 'setupError' => $setupError];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Inscription bénévoles</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="assets/app.css">
<script src="assets/i18n.js"></script>
</head>
<body class="ro">
<header class="band">
  <div class="wrap band-in">
    <div class="brand">
      <a class="mark" href="./" title="Tous les événements" data-i18n-title="Tous les événements">YV</a>
      <div class="brand-t" data-i18n="YOUL VAT - Régie Bénévoles">YOUL VAT - Régie Bénévoles</div>
    </div>
    <div class="evcenter">
      <img id="evImg" class="evimg" alt="" hidden>
      <div class="brand-b">
        <div class="evline">
          <label for="evSel" class="sr" data-i18n="Événement">Événement</label>
          <select id="evSel"></select>
        </div>
        <div class="brand-s" id="evMeta" data-i18n="Chargement…">Chargement…</div>
      </div>
    </div>
    <div class="evpick">
      <a class="btn danger fill sm" id="changeLink" href="contact.php" data-i18n="Changer / me désister">Changer / me désister</a>
      <a class="btn band-btn sm" id="contactLink" href="contact.php" data-i18n="Contact">Contact</a>
      <a class="btn band-btn sm" id="planLink" href="index.php" data-i18n="Voir le planning">Voir le planning</a>
      <div class="langsw" role="group" aria-label="Yezh / Langue"></div>
    </div>
  </div>
</header>
<main class="wrap" id="main"><div class="empty" data-i18n="Chargement des créneaux…">Chargement des créneaux…</div></main>
<div id="toast" role="status" hidden></div>

<script>
window.REGIE=<?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
(function(){
const {t,tn}=I18N;
I18N.applyStatic();
const CFG=window.REGIE||{};
const COLORS=['#3B6FD8','#D8573B','#2E9E7A','#9B59C7','#C99A1A','#D44C8C','#3E98B3','#7A8B2E'];
const S={events:[],postes:[],creneaux:[],benevoles:[],affs:[],eventId:new URLSearchParams(location.search).get('event')||null,day:'',poste:'',full:false,me:{prenom:'',nom:'',tel:'',email:''},loaded:false};
const $=s=>document.querySelector(s);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ls={get(k){try{return localStorage.getItem(k)}catch(e){return null}},set(k,v){try{localStorage.setItem(k,v)}catch(e){}}};
try{Object.assign(S.me,JSON.parse(ls.get('rb.me')||'{}'))}catch(e){}

/* temps : mêmes règles que l'interface principale (avant 6h = nuit du jour indiqué) */
const tm=x=>{const p=String(x||'0:0').split(':').map(Number);return p[0]*60+(p[1]||0)};
const dayN=j=>Math.round(Date.parse(j+'T00:00:00Z')/864e5);
function span(c){const s=dayN(c.jour)*1440+tm(c.debut)+(tm(c.debut)<360?1440:0);let d=(tm(c.fin)-tm(c.debut)+1440)%1440;if(!d)d=1440;return [s,s+d]}
const overlaps=(a,b)=>{const x=span(a),y=span(b);return x[0]<y[1]&&y[0]<x[1]};
const dur=c=>{const x=span(c);return (x[1]-x[0])/60};
const fmtH=h=>(Math.round(h*10)/10).toString().replace('.',',')+' h';
const fmtDay=I18N.fmtDay;
const shortDay=j=>fmtDay(j,{weekday:'short',day:'numeric',month:'short'});
const hLab=x=>x.replace(':00','h').replace(':','h');
const hours=c=>hLab(c.debut)+' – '+hLab(c.fin);

const ev=()=>S.events.find(e=>e.id===S.eventId);
const poste=id=>S.postes.find(p=>p.id===id);
const pColor=p=>COLORS[((p&&p.couleur)||0)%COLORS.length];
const actifs=cid=>S.affs.filter(a=>a.creneauId===cid&&a.statut!=='absent');
const left=c=>Math.max(0,(+c.besoin||0)-actifs(c.id).length);
const norm=s=>String(s||'').trim().replace(/\s+/g,' ').toLocaleLowerCase('fr');
function meBen(){const p=norm(S.me.prenom),n=norm(S.me.nom);if(!p||!n)return null;return S.benevoles.find(b=>norm(b.prenom)===p&&norm(b.nom)===n)||null}
function myShifts(){const b=meBen();if(!b)return [];return S.affs.filter(a=>a.benevoleId===b.id&&a.statut!=='absent').map(a=>S.creneaux.find(c=>c.id===a.creneauId)).filter(c=>c&&c.eventId===S.eventId).sort((x,y)=>span(x)[0]-span(y)[0])}
const groupBy=(arr,key)=>arr.reduce((m,x)=>{const k=key(x);if(!m.has(k))m.set(k,[]);m.get(k).push(x);return m},new Map());
const creneaux=()=>S.creneaux.filter(c=>c.eventId===S.eventId).sort((a,b)=>span(a)[0]-span(b)[0]);
const placesLibres=l=>tn(l,'{n} place libre','{n} places libres');

function toast(msg){const el=$('#toast');el.textContent=msg;el.hidden=false;clearTimeout(toast._t);toast._t=setTimeout(()=>{el.hidden=true},3500)}
async function api(body){
  const opt=body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CFG.csrf},body:JSON.stringify(body)}:{};
  let r,j;
  try{r=await fetch(body?'api.php':'api.php?action=all',Object.assign({credentials:'same-origin',cache:'no-store'},opt));j=await r.json()}
  catch(e){throw {message:t('Serveur injoignable. Vérifiez votre connexion.')}}
  if(!r.ok||!j.ok){
    /* le chevauchement renvoie ses éléments séparément pour pouvoir être traduit */
    const msg=j&&j.error==='overlap'?t('Vous êtes déjà inscrit·e à la même heure : {poste} ({debut}–{fin}).',j):(j&&j.message&&t(j.message));
    throw {code:j&&j.error,message:msg};
  }
  return j;
}
async function load(){
  const j=await api();
  S.events=j.events;S.postes=j.postes;S.creneaux=j.creneaux;S.benevoles=j.benevoles;S.affs=j.affectations;S.loaded=true;
}
const evImgUrl=e=>e&&/^https:\/\//.test(e.imageUrl||'')?e.imageUrl:'';
const isPast=e=>(e.fin||e.debut)<new Date().toISOString().slice(0,10);
const evWhen=e=>[e.lieu,e.debut?(fmtDay(e.debut,{day:'numeric',month:'long'})+(e.fin&&e.fin!==e.debut?' → '+fmtDay(e.fin,{day:'numeric',month:'long',year:'numeric'}):'')):''].filter(Boolean).join(' · ');
/* même en-tête que index.php : logo + sélecteur (événements ouverts aux inscriptions) au centre */
function renderHeader(){
  const e=ev();const open=S.events.filter(x=>!isPast(x)||x.id===S.eventId).sort((a,b)=>String(a.debut).localeCompare(b.debut));
  const sel=$('#evSel');
  sel.innerHTML=`<option value="">${open.length?t('Tous les événements'):t('Aucun événement')}</option>`+open.map(x=>`<option value="${esc(x.id)}"${x.id===S.eventId?' selected':''}>${esc(x.nom)}</option>`).join('');
  sel.disabled=!open.length;
  const img=$('#evImg');const iu=evImgUrl(e);img.hidden=!iu;if(iu&&img.getAttribute('src')!==iu)img.src=iu;
  $('#evMeta').textContent=e?evWhen(e):t('Choisissez un événement');
  $('#planLink').href='index.php'+(e?'?event='+encodeURIComponent(e.id):'');
  $('#contactLink').href=$('#changeLink').href='contact.php'+(e?'?event='+encodeURIComponent(e.id):'');
  document.title=(e?e.nom+' · ':'')+t('Inscription bénévoles');
}
/* sans ?event= (ou événement inconnu) : liste des événements ouverts aux inscriptions */
function rList(){
  const evs=S.events.filter(e=>!isPast(e)).sort((a,b)=>String(a.debut).localeCompare(b.debut));
  const lost=S.eventId?`<div class="notice">${t('Cet événement n’existe pas ou a été supprimé.')}</div>`:'';
  const cards=evs.map(e=>{const free=S.creneaux.filter(c=>c.eventId===e.id).reduce((s,c)=>s+left(c),0);const iu=evImgUrl(e);
    return `<article class="panel evcard"><a class="evcard-h" href="?event=${encodeURIComponent(e.id)}">${iu?`<img class="evcard-img" src="${esc(iu)}" alt="" loading="lazy">`:''}<span><h3>${esc(e.nom)}</h3><span class="muted" style="font-size:13px">${esc([e.lieu,fmtDay(e.debut,{day:'numeric',month:'long',year:'numeric'})].filter(Boolean).join(' · '))}</span></span></a>
      <div class="actions" style="justify-content:flex-start;align-items:center"><a class="btn pri" href="?event=${encodeURIComponent(e.id)}">${t('Choisir mes créneaux')}</a><span class="muted num">${placesLibres(free)}</span></div></article>`}).join('');
  return `${lost}<section class="sec"><h2>${t('Événements')}</h2>${cards?`<div class="evlist">${cards}</div>`:`<div class="panel empty">${t('Aucune inscription ouverte pour l’instant.')}</div>`}</section>`;
}

/* ---------- rendu ---------- */
function render(){
  const m=$('#main');
  if(CFG.setupError){m.innerHTML=`<div class="notice">${esc(t(CFG.setupError))}</div>`;return}
  if(!S.loaded){m.innerHTML=`<div class="notice">${t('Impossible de charger les créneaux. Réessayez dans un instant.')}</div>`;return}
  const e=ev();
  renderHeader();
  if(!e){m.innerHTML=rList();return}

  const b=meBen();const mine=myShifts();
  const cs=creneaux();
  const days=[...new Set(cs.map(c=>c.jour))].sort();
  if(S.day&&!days.includes(S.day))S.day='';
  const shown=cs.filter(c=>(!S.day||c.jour===S.day)&&(!S.poste||c.posteId===S.poste)&&(S.full||left(c)>0||mine.includes(c)));
  const open=cs.filter(c=>left(c)>0);

  const intro=`<section class="panel" style="padding:14px 16px;display:flex;flex-direction:column;gap:6px">
    <h2>${t('Devenir bénévole')}</h2>
    <p style="margin:0">${t('Choisissez un ou plusieurs créneaux libres, en indiquant vos prénom, nom, téléphone et e-mail.')} <span class="muted">${t('Un créneau qui commence avant 6h compte dans la nuit du jour affiché.')}</span></p>
    <p style="margin:0"><b>${t('Pour des raisons d’organisation, veuillez nous contacter si vous souhaitez changer de créneau ou êtes contraint·e de vous désister, en utilisant le bouton rouge.')}</b></p>
    <p class="muted num" style="margin:0">${tn(open.length,'{n} créneau à pourvoir','{n} créneaux à pourvoir')} · ${placesLibres(open.reduce((s,c)=>s+left(c),0))}${e.contact?` · ${t('Contact')} : <b style="user-select:all">${esc(e.contact)}</b>`:''}</p></section>`;

  const ident=`<section class="sec"><h2>${t('Vous êtes')}</h2><form class="panel ident" id="meFrm" autocomplete="on">
    <div class="field"><label for="prenom">${t('Prénom')} *</label><input id="prenom" name="prenom" autocomplete="given-name" maxlength="60" value="${esc(S.me.prenom)}" required></div>
    <div class="field"><label for="nom">${t('Nom')} *</label><input id="nom" name="nom" autocomplete="family-name" maxlength="60" value="${esc(S.me.nom)}" required></div>
    <div class="field"><label for="tel">${t('Téléphone')} *</label><input id="tel" name="tel" type="tel" autocomplete="tel" maxlength="25" value="${esc(S.me.tel)}" placeholder="06 12 34 56 78" required></div>
    <div class="field"><label for="email">${t('E-mail')} *</label><input id="email" name="email" type="email" autocomplete="email" maxlength="120" value="${esc(S.me.email)}" placeholder="${t('prenom@exemple.bzh')}" required></div>
    <input name="website" id="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px">
    <span class="muted" style="grid-column:1/-1;font-size:12.5px">${t('Vos prénom et nom apparaissent sur le planning public.')} ${t('Téléphone et e-mail sont obligatoires : ils servent à vous prévenir d’un changement. Ils ne sont visibles que par les organisateurs.')}</span>
  </form></section>`;

  const mineHtml=b?`<section class="sec mine"><div class="sec-h"><h2>${t('Mes créneaux')} <span class="muted num" style="font-size:16px">(${mine.length})</span></h2>${mine.length?`<button class="btn sm" type="button" data-act="copy">${t('Copier')}</button>`:''}</div>
    <div class="panel list">${mine.length?mine.map(c=>{const p=poste(c.posteId);return `<div class="row"><div class="when"><b>${esc(shortDay(c.jour))}</b><span class="mono muted">${esc(hours(c))}</span></div><span class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(p?p.nom:'')}</span></div>`}).join(''):`<div class="empty" style="padding:14px">${t('Aucun créneau pour l’instant.')}</div>`}</div>
    ${mine.length?`<p class="muted" style="margin:0;font-size:12.5px">${t('Un empêchement ? Prévenez l’organisation')}${e.contact?` : <b>${esc(e.contact)}</b>`:''}.</p>`:''}</section>`:'';

  const chips=`<div class="days"><button type="button" class="chip" aria-pressed="${!S.day}" data-act="day" data-id="" style="text-transform:none">${t('Tous')}</button>${days.map(d=>{const n=cs.filter(c=>c.jour===d&&left(c)>0).length;return `<button type="button" class="chip" aria-pressed="${d===S.day}" data-act="day" data-id="${d}">${esc(shortDay(d))}<small class="num">${n}</small></button>`}).join('')}</div>`;
  const postesUsed=S.postes.filter(p=>cs.some(c=>c.posteId===p.id)).sort((a,b)=>String(a.nom).localeCompare(b.nom,'fr'));
  const filters=`<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <label class="sr" for="posteSel">${t('Poste')}</label><select id="posteSel" class="search" style="width:auto;max-width:100%"><option value="">${t('Tous les postes')}</option>${postesUsed.map(p=>`<option value="${esc(p.id)}"${p.id===S.poste?' selected':''}>${esc(p.nom)}</option>`).join('')}</select>
    <label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" id="fullChk"${S.full?' checked':''}> ${t('Afficher les créneaux complets')}</label></div>`;

  /* par jour, puis par poste (dans l'ordre de leur premier créneau) : le nom du poste et ses consignes ne sont affichés qu'une fois */
  const slot=c=>{const l=left(c);const me=mine.includes(c);const busy=!me&&mine.some(o=>overlaps(o,c));
    const btn=me?`<span class="pill ok">${t('Inscrit·e ✓')}</span>`:!l?`<span class="pill neu">${t('Complet')}</span>`:busy?`<span class="pill warn">${t('Déjà pris·e')}</span>`:`<button class="btn pri" type="button" data-act="signup" data-id="${esc(c.id)}">${t('Je m’inscris')}</button>`;
    return `<div class="slot${!l&&!me?' off':''}" id="c-${esc(c.id)}"><div class="meta"><span class="t">${esc(hours(c))}</span><span class="muted num">${fmtH(dur(c))}</span><span class="pill ${l?(l===+c.besoin?'crit':'warn'):'ok'} num">${l?placesLibres(l):t('complet')}</span></div>${btn}${c.note?`<div class="note">${esc(c.note)}</div>`:''}</div>`};
  let list='';
  groupBy(shown,c=>c.jour).forEach((dcs,d)=>{
    list+=`<h3 class="day-h">${esc(fmtDay(d))}</h3><div class="pgroups">`;
    groupBy(dcs,c=>c.posteId).forEach((pcs,pid)=>{const p=poste(pid);
      list+=`<div class="panel pgroup"><div class="pg-h"><span class="dot" style="background:${pColor(p)}"></span><span class="pg-t">${esc(p?p.nom:'')}</span></div>${p&&p.description?`<div class="pg-d">${esc(p.description)}</div>`:''}${pcs.map(slot).join('')}</div>`});
    list+='</div>';
  });
  if(!shown.length)list=`<div class="panel empty">${cs.length?t('Aucun créneau libre avec ces filtres.'):t('Aucun créneau pour l’instant.')}</div>`;

  m.innerHTML=`${intro}${ident}${mineHtml}<section class="sec"><h2>${t('Créneaux')}</h2>${chips}${filters}<div>${list}</div></section>`;
}

/* ---------- actions ---------- */
function readMe(){const v=id=>{const el=$('#'+id);return el?el.value:''};S.me={prenom:v('prenom'),nom:v('nom'),tel:v('tel'),email:v('email')};ls.set('rb.me',JSON.stringify(S.me))}
const ME_FIELDS=['prenom','nom','tel','email'];
function keepFocus(fn){const a=document.activeElement;const id=a&&a.id;const pos=a&&a.selectionStart;fn();if(id){const el=document.getElementById(id);if(el){el.focus();if(pos!=null&&el.setSelectionRange)try{el.setSelectionRange(pos,pos)}catch(e){}}}}

document.addEventListener('click',async e=>{
  const el=e.target.closest('[data-act]');if(!el)return;
  const act=el.dataset.act,id=el.dataset.id;
  if(act==='day'){S.day=id;render()}
  else if(act==='copy'){const ev0=ev();const txt=`${S.me.prenom.trim()} ${S.me.nom.trim()} – ${ev0?ev0.nom:''}\n`+myShifts().map(c=>{const p=poste(c.posteId);return `• ${shortDay(c.jour)} ${hours(c)} : ${p?p.nom:''}`}).join('\n');
    try{await navigator.clipboard.writeText(txt);toast(t('Planning copié.'))}catch(err){toast(t('Copie impossible sur cet appareil.'))}}
  else if(act==='signup'){
    readMe();
    const prenom=S.me.prenom.trim(),nom=S.me.nom.trim(),tel=S.me.tel.trim(),email=S.me.email.trim();
    const miss=ME_FIELDS.find(k=>!S.me[k].trim());
    if(miss){toast(t('Indiquez d’abord vos prénom, nom, téléphone et e-mail.'));const f=$('#'+miss);f.focus();f.scrollIntoView({block:'center'});return}
    if(!el.classList.contains('armed')){el.classList.add('armed');el.textContent=t('Confirmer ?');setTimeout(()=>{if(el.isConnected){el.classList.remove('armed');el.textContent=t('Je m’inscris')}},4000);return}
    el.disabled=true;el.textContent='…';
    try{
      await api({action:'signup',creneauId:id,prenom,nom,tel,email,website:($('#website')||{}).value||''});
      const c=S.creneaux.find(x=>x.id===id);const p=c&&poste(c.posteId);
      toast(t('Merci {p} ! Inscrit·e : {poste}, {quand}.',{p:prenom,poste:p?p.nom:'',quand:c?shortDay(c.jour)+' '+hours(c):''}));
    }catch(err){toast((err&&err.message)||t('Inscription impossible. Réessayez.'))}
    try{await load()}catch(err){}
    render();
  }
});
document.addEventListener('change',e=>{
  if(e.target.id==='evSel'){location.href=location.pathname+(e.target.value?'?event='+encodeURIComponent(e.target.value):'');return}
  if(e.target.id==='posteSel'){S.poste=e.target.value;render()}
  else if(e.target.id==='fullChk'){S.full=e.target.checked;render()}
});
/* le nom tapé met à jour « Mes créneaux » et les créneaux déjà pris */
document.addEventListener('input',e=>{
  if(!ME_FIELDS.includes(e.target.id))return;
  readMe();
  if(e.target.id==='prenom'||e.target.id==='nom'){clearTimeout(render._t);render._t=setTimeout(()=>keepFocus(render),400)}
});
document.addEventListener('submit',e=>{if(e.target.id==='meFrm')e.preventDefault()});

async function boot(){
  if(CFG.setupError){render();return}
  try{await load()}catch(e){toast((e&&e.message)||t('Chargement impossible.'))}
  const h=decodeURIComponent(location.hash.slice(1));
  const target=h.startsWith('c-')&&S.creneaux.find(c=>c.id===h.slice(2));
  if(target){S.day=target.jour;if(!left(target))S.full=true}
  render();
  if(target){const el=document.getElementById(h);if(el){el.scrollIntoView({block:'center'});el.style.outline='2px solid var(--accent)'}}
  setInterval(async()=>{
    if(document.hidden)return;const a=document.activeElement;if(a&&ME_FIELDS.includes(a.id))return;
    try{await load();keepFocus(render)}catch(e){}
  },20000);
}
boot();
})();
</script>
</body>
</html>
