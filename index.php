<?php
require_once __DIR__ . '/lib/auth.php';
start_session();
$setupError = null;
try { db(); } catch (Throwable $e) { $setupError = $e instanceof DbSetupError ? $e->getMessage() : 'Connexion à la base impossible : ' . $e->getMessage(); }
$boot = [
    'csrf'        => csrf_token(),
    'admin'       => is_admin(),
    'authEnabled' => auth_enabled(),
    'setupError'  => $setupError,
];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars(config()['app_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="assets/app.css">
<script src="assets/i18n.js"></script>
</head>
<body<?= is_admin() ? '' : ' class="ro"' ?>>

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
          <button class="btn band-btn sm adm" id="evEdit" type="button" hidden data-i18n="Modifier">Modifier</button>
        </div>
        <div class="brand-s" id="evMeta" data-i18n="Chargement…">Chargement…</div>
      </div>
    </div>
    <div class="evpick">
      <button class="btn band-btn sm adm" id="evNew" type="button" data-i18n="+ Événement">+ Événement</button>
      <a class="btn pri sm" id="signupLink" href="inscription.php" data-i18n="S’inscrire">S’inscrire</a>
      <button class="btn band-btn sm" id="login" type="button" hidden data-i18n="Organisateurs">Organisateurs</button>
      <button class="btn band-btn sm" id="logout" type="button" hidden data-i18n="Déconnexion">Déconnexion</button>
      <div class="langsw" role="group" aria-label="Yezh / Langue"></div>
    </div>
  </div>
  <nav class="wrap tabs" role="tablist" aria-label="Sections">
    <button class="tab" role="tab" data-tab="dash" type="button" data-i18n="Tableau de bord">Tableau de bord</button>
    <button class="tab" role="tab" data-tab="plan" type="button" data-i18n="Planning">Planning</button>
    <button class="tab" role="tab" data-tab="bens" type="button" data-i18n="Bénévoles">Bénévoles</button>
    <button class="tab" role="tab" data-tab="postes" type="button" data-i18n="Postes &amp; créneaux">Postes &amp; créneaux</button>
    <a class="tab" id="contactTab" href="contact.php" data-i18n="Contact">Contact</a>
  </nav>
</header>
<main class="wrap" id="main"><div class="empty" data-i18n="Chargement des données…">Chargement des données…</div></main>
<div id="scrim" hidden></div>
<aside id="drawer" hidden aria-modal="true" role="dialog"></aside>
<div id="toast" role="status" hidden></div>

<script>
window.REGIE=<?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
(function(){
const {t,tn}=I18N;
I18N.applyStatic();
const COLORS=['#3B6FD8','#D8573B','#2E9E7A','#9B59C7','#C99A1A','#D44C8C','#3E98B3','#7A8B2E'];
const COLS={events:'events',postes:'postes',creneaux:'creneaux',benevoles:'benevoles',affs:'affectations'};
const STATUTS=[['prevu',t('Prévu')],['present',t('Présent')],['absent',t('Absent')]];
const S={events:[],postes:[],creneaux:[],benevoles:[],affs:[],eventId:null,tab:'dash',day:null,q:'',ro:true};
S.eventId=new URLSearchParams(location.search).get('event')||null; /* l'événement vient de l'URL uniquement */
const DR={kind:null,id:null,extra:null};
const CFG=window.REGIE||{};
const REFRESH_MS=15000;
let db=false,lastSnap='';

const $=s=>document.querySelector(s);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ls={get(k){try{return localStorage.getItem(k)}catch(e){return null}},set(k,v){try{localStorage.setItem(k,v)}catch(e){}}};
S.tab=ls.get('rb.tab')||'dash';
/* vue du planning : liste par défaut sur téléphone, grille sinon ; le choix est mémorisé */
S.view=ls.get('rb.view')||(window.matchMedia&&matchMedia('(max-width:560px)').matches?'list':'grid');

/* ---------- temps ---------- */
const tm=x=>{const p=String(x||'0:0').split(':').map(Number);return p[0]*60+(p[1]||0)};
const dayN=j=>Math.round(Date.parse(j+'T00:00:00Z')/864e5);
const nToDay=n=>new Date(n*864e5).toISOString().slice(0,10);
const NIGHT=360; // un créneau qui commence avant 6h appartient à la nuit du jour indiqué
function span(c){const s=dayN(c.jour)*1440+tm(c.debut)+(tm(c.debut)<NIGHT?1440:0);let d=(tm(c.fin)-tm(c.debut)+1440)%1440;if(!d)d=1440;return [s,s+d]}
const dur=c=>{const x=span(c);return (x[1]-x[0])/60};
const fmtH=h=>(Math.round(h*10)/10).toString().replace('.',',')+' h';
const fmtDay=I18N.fmtDay;
const shortDay=j=>fmtDay(j,{weekday:'short',day:'numeric',month:'short'});
const hLab=x=>x.replace(':00','h').replace(':','h');
const hours=c=>hLab(c.debut)+' – '+hLab(c.fin);

/* ---------- données dérivées ---------- */
const ev=()=>S.events.find(e=>e.id===S.eventId);
const P=()=>S.postes.filter(p=>p.eventId===S.eventId).sort((a,b)=>String(a.nom).localeCompare(b.nom,'fr'));
const C=()=>S.creneaux.filter(c=>c.eventId===S.eventId).sort((a,b)=>span(a)[0]-span(b)[0]);
const A=()=>S.affs.filter(a=>a.eventId===S.eventId);
const cren=id=>S.creneaux.find(c=>c.id===id);
const ben=id=>S.benevoles.find(b=>b.id===id);
const poste=id=>S.postes.find(p=>p.id===id);
const affsOf=cid=>S.affs.filter(a=>a.creneauId===cid);
const actifs=cid=>affsOf(cid).filter(a=>a.statut!=='absent');
const pColor=p=>COLORS[((p&&p.couleur)||0)%COLORS.length];
const statutLab=a=>(STATUTS.find(s=>s[0]===(a.statut||'prevu'))||STATUTS[0])[1];
const evUrl=id=>location.pathname+(id?'?event='+encodeURIComponent(id):'');
const signupUrl=cid=>'inscription.php?event='+encodeURIComponent(S.eventId||'')+(cid?'#c-'+encodeURIComponent(cid):'');
const bName=b=>b?((b.prenom||'')+' '+(b.nom||'')).trim():t('Bénévole supprimé');
const posteNom=p=>p?p.nom:t('Poste supprimé');
function shiftsOf(bid,all){return S.affs.filter(a=>a.benevoleId===bid).map(a=>({a,c:cren(a.creneauId)})).filter(x=>x.c&&(all||x.c.eventId===S.eventId)).sort((x,y)=>span(x.c)[0]-span(y.c)[0])}
function overlaps(c1,c2){const a=span(c1),b=span(c2);return a[0]<b[1]&&b[0]<a[1]}
function conflictsOf(bid){const sh=shiftsOf(bid,true).filter(x=>x.a.statut!=='absent');const out=new Set();for(let i=0;i<sh.length;i++)for(let j=i+1;j<sh.length;j++)if(overlaps(sh[i].c,sh[j].c)){out.add(sh[i].c.id);out.add(sh[j].c.id)}return out}
function busyFor(bid,c){return shiftsOf(bid,true).some(x=>x.c.id!==c.id&&x.a.statut!=='absent'&&overlaps(x.c,c))}
function hoursOf(bid){return shiftsOf(bid).filter(x=>x.a.statut!=='absent').reduce((s,x)=>s+dur(x.c),0)}
function fillState(c){const n=actifs(c.id).length,b=+c.besoin||0;return n>=b?'full':(n===0?'none':'part')}
function eventDays(){const e=ev();const set=new Set();if(e&&e.debut){const a=dayN(e.debut),b=dayN(e.fin||e.debut);for(let i=a;i<=b&&i-a<60;i++)set.add(nToDay(i))}C().forEach(c=>set.add(c.jour));return [...set].sort()}

/* ---------- écriture ---------- */
function toast(msg){const el=$('#toast');el.textContent=msg;el.hidden=false;clearTimeout(toast._t);toast._t=setTimeout(()=>{el.hidden=true},2800)}
async function api(body){
  const opt=body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CFG.csrf},body:JSON.stringify(body)}:{};
  let r,j;
  try{r=await fetch(body?'api.php':'api.php?action=all',Object.assign({credentials:'same-origin',cache:'no-store'},opt));j=await r.json()}
  catch(e){throw {code:'network',message:t('Serveur injoignable. Vérifiez la connexion.')}}
  if(!r.ok||!j.ok)throw {code:j&&j.error,message:j&&j.message&&t(j.message)};
  return j;
}
/* écritures : une table, puis rechargement complet */
const TBL=k=>COLS[k]||k;
const create=(k,data)=>api({action:'create',table:TBL(k),data});
const update=(k,id,data)=>api({action:'update',table:TBL(k),id,data});
const remove=(k,id)=>api({action:'delete',table:TBL(k),id});
async function w(fn,ok){
  if(!db){toast(t('La base de données n’est pas disponible.'));return false}
  if(S.ro){toast(t('Réservé aux organisateurs : connectez-vous.'));return false}
  try{await fn();if(ok)toast(ok);await load();return true}
  catch(e){const c=e&&e.code;
    if(c==='auth'||c==='csrf'){toast(e.message||t('Session expirée.'));setTimeout(()=>location.reload(),1500)}
    else toast((e&&e.message)||t('Enregistrement impossible pour le moment. Réessayez.'));
    await load().catch(()=>{});render();
    return false}
}

/* ---------- rendu principal ---------- */
function renderHeader(){
  const sel=$('#evSel');const e=ev();
  sel.innerHTML=`<option value="">${S.events.length?t('Tous les événements'):t('Aucun événement')}</option>`+evSorted().map(x=>`<option value="${esc(x.id)}"${x.id===S.eventId?' selected':''}>${esc(x.nom)}</option>`).join('');
  sel.disabled=!S.events.length;$('#evEdit').hidden=!e;
  const img=$('#evImg');const iu=evImgUrl(e);img.hidden=!iu;if(iu&&img.getAttribute('src')!==iu)img.src=iu;
  $('#signupLink').href=signupUrl();
  $('#contactTab').href='contact.php'+(e?'?event='+encodeURIComponent(e.id):'');
  $('#evMeta').textContent=e?evWhen(e):(db?t('Choisissez un événement'):'');
  document.querySelector('.tabs').hidden=!e;
  document.title=(e?e.nom+' · ':'')+t('Régie Bénévoles');
  document.querySelectorAll('.tab').forEach(x=>x.setAttribute('aria-selected',String(x.dataset.tab===S.tab)));
}
function render(){
  renderHeader();
  const m=$('#main');
  if(CFG.setupError){m.innerHTML=`<div class="notice">${esc(t(CFG.setupError))}</div>`;return}
  if(!db&&render.noDb){m.innerHTML=`<div class="notice">${t('Impossible de charger les données. Vérifiez la connexion puis rechargez la page.')}</div>`;return}
  if(!db){m.innerHTML=`<div class="empty">${t('Chargement des données…')}</div>`;return}
  if(!ev()){m.innerHTML=rList();return}
  const html={dash:rDash,plan:rPlan,bens:rBens,postes:rPostes}[S.tab]||rDash;
  const ro=S.ro&&(S.tab==='dash'||S.tab==='plan')?`<div class="panel cta"><p><b>${t('Envie de donner un coup de main ?')}</b> <span class="muted">${t('Choisissez un créneau libre, il suffit de votre prénom et de votre nom.')}</span></p><a class="btn pri" href="${esc(signupUrl())}">${t('S’inscrire comme bénévole')}</a></div>`:'';
  m.innerHTML=ro+html();
  if(S.tab==='bens'){const q=$('#q');if(q&&render.focusQ){q.focus();q.setSelectionRange(q.value.length,q.value.length)}}
  if(DR.kind&&!DR.form)renderDrawer();
}

/* ---------- liste des événements (page sans ?event=) ---------- */
const evImgUrl=e=>e&&/^https:\/\//.test(e.imageUrl||'')?e.imageUrl:'';
const evWhen=e=>[e.lieu,e.debut?(fmtDay(e.debut,{day:'numeric',month:'long'})+(e.fin&&e.fin!==e.debut?' → '+fmtDay(e.fin,{day:'numeric',month:'long',year:'numeric'}):'')):''].filter(Boolean).join(' · ');
const isPast=e=>(e.fin||e.debut)<new Date().toISOString().slice(0,10);
/* à venir d'abord (le plus proche en tête), puis les passés (le plus récent en tête) */
const evSorted=()=>S.events.slice().sort((a,b)=>isPast(a)-isPast(b)||(isPast(a)?String(b.debut).localeCompare(a.debut):String(a.debut).localeCompare(b.debut)));
function rList(){
  const lost=S.eventId?`<div class="notice">${t('Cet événement n’existe pas ou a été supprimé. Choisissez-en un ci-dessous.')}</div>`:'';
  const cards=evSorted().map(e=>{
    const cs=S.creneaux.filter(c=>c.eventId===e.id);const need=cs.reduce((s,c)=>s+(+c.besoin||0),0);
    const filled=cs.reduce((s,c)=>s+Math.min(actifs(c.id).length,+c.besoin||0),0);const r=need?filled/need:0;
    const iu=evImgUrl(e);
    return `<article class="panel evcard${isPast(e)?' past':''}">
      <a class="evcard-h" href="${esc(evUrl(e.id))}">${iu?`<img class="evcard-img" src="${esc(iu)}" alt="" loading="lazy">`:`<span class="mark evcard-mark">${esc(String(e.nom||'?').trim().slice(0,2).toUpperCase())}</span>`}
        <span><h3>${esc(e.nom)}</h3><span class="muted" style="font-size:13px">${esc(evWhen(e))}</span></span></a>
      ${cs.length?`<div style="display:flex;flex-direction:column;gap:5px"><div class="bar-h" style="margin:0"><span class="muted num" style="font-size:12.5px">${tn(cs.length,'{n} créneau','{n} créneaux')} · ${t('{f}/{n} places pourvues',{f:filled,n:need})}</span>${isPast(e)?`<span class="pill neu">${t('terminé')}</span>`:''}</div>${meter(r,rateColor(r))}</div>`:`<span class="muted" style="font-size:13px">${t('Aucun créneau pour l’instant.')}</span>`}
      <div class="actions" style="justify-content:flex-start"><a class="btn pri" href="${esc(evUrl(e.id))}">${t('Voir le planning')}</a>${isPast(e)?'':`<a class="btn" href="inscription.php?event=${encodeURIComponent(e.id)}">${t('S’inscrire')}</a>`}</div>
    </article>`}).join('');
  return `${lost}<section class="sec"><div class="sec-h"><h2>${t('Événements')}</h2><button class="btn pri adm" type="button" data-act="ev-new">${t('+ Événement')}</button></div>
    ${cards?`<div class="evlist">${cards}</div>`:`<div class="panel empty">${S.ro?t('Aucun planning publié pour l’instant.'):t('Commencez par créer votre festival : dates, lieu, puis vos postes et créneaux.')}</div>`}</section>`;
}

/* ---------- tableau de bord ---------- */
function meter(r,color){return `<div class="meter"><i style="width:${Math.min(100,Math.round(r*100))}%;background:${color}"></i></div>`}
function rateColor(r){return r>=1?'var(--ok)':r>=.6?'var(--warn)':'var(--crit)'}
function rDash(){
  const cs=C();const need=cs.reduce((s,c)=>s+(+c.besoin||0),0);
  const filled=cs.reduce((s,c)=>s+Math.min(actifs(c.id).length,+c.besoin||0),0);
  const rate=need?filled/need:0;
  const inc=cs.filter(c=>actifs(c.id).length<(+c.besoin||0));
  const bensEv=new Set(A().filter(a=>a.statut!=='absent').map(a=>a.benevoleId));
  const hrs=A().filter(a=>a.statut!=='absent').reduce((s,a)=>{const c=cren(a.creneauId);return s+(c?dur(c):0)},0);
  const confl=[...bensEv].filter(id=>{const cs2=conflictsOf(id);return [...cs2].some(cid=>{const c=cren(cid);return c&&c.eventId===S.eventId})});
  const kpis=`<div class="kpis">
    <div class="kpi"><span class="kpi-l">${t('Remplissage')}</span><span class="kpi-v num">${Math.round(rate*100)} %</span>${meter(rate,rateColor(rate))}<span class="kpi-s num">${t('{f} places pourvues sur {n}',{f:filled,n:need})}</span></div>
    <div class="kpi"><span class="kpi-l">${t('Places à pourvoir')}</span><span class="kpi-v num" style="color:${need-filled?'var(--crit)':'var(--ok)'}">${need-filled}</span><span class="kpi-s">${tn(inc.length,'{n} créneau incomplet','{n} créneaux incomplets')}</span></div>
    <div class="kpi"><span class="kpi-l">${t('Bénévoles mobilisés')}</span><span class="kpi-v num">${bensEv.size}</span><span class="kpi-s num">${t('sur {n} dans l’annuaire',{n:S.benevoles.length})}</span></div>
    <div class="kpi"><span class="kpi-l">${t('Heures planifiées')}</span><span class="kpi-v num">${fmtH(hrs).replace(' h','')}<small style="font-size:18px"> h</small></span><span class="kpi-s">${bensEv.size?t('{h} par bénévole en moyenne',{h:fmtH(hrs/bensEv.size)}):'—'}</span></div>
  </div>`;
  const alert=confl.length?`<div class="notice">⚠ ${tn(confl.length,'{n} bénévole a des créneaux qui se chevauchent :','{n} bénévoles ont des créneaux qui se chevauchent :')} ${confl.map(id=>`<a href="#" data-act="ben" data-id="${esc(id)}" style="color:inherit">${esc(bName(ben(id)))}</a>`).join(', ')}.</div>`:'';
  const pri=inc.length?inc.slice(0,14).map(c=>{const p=poste(c.posteId);const n=actifs(c.id).length;const miss=(+c.besoin||0)-n;
    return `<div class="row click" data-act="cren" data-id="${esc(c.id)}"><div class="when"><b>${esc(shortDay(c.jour))}</b><span class="mono muted">${esc(hours(c))}</span></div><span class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(posteNom(p))}</span><span class="pill ${n?'warn':'crit'} num">${t('manque {n}',{n:miss})}</span></div>`}).join('')+(inc.length>14?`<div class="row"><span></span><span class="muted">${t('+ {n} autres créneaux incomplets',{n:inc.length-14})}</span><span></span></div>`:'')
    :`<div class="empty">${cs.length?t('Tous les créneaux sont pourvus.'):t('Aucun créneau pour l’instant. Ajoutez des postes et créneaux.')}</div>`;
  const byP=P().map(p=>{const pc=cs.filter(c=>c.posteId===p.id);const nd=pc.reduce((s,c)=>s+(+c.besoin||0),0);const f=pc.reduce((s,c)=>s+Math.min(actifs(c.id).length,+c.besoin||0),0);const r=nd?f/nd:0;
    return `<div><div class="bar-h"><span class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(p.nom)}</span><span class="num muted">${f}/${nd}</span></div>${meter(r,rateColor(nd?r:1))}</div>`}).join('')||`<div class="muted">${t('Aucun poste.')}</div>`;
  const byD=eventDays().map(d=>{const dc=cs.filter(c=>c.jour===d);const nd=dc.reduce((s,c)=>s+(+c.besoin||0),0);const f=dc.reduce((s,c)=>s+Math.min(actifs(c.id).length,+c.besoin||0),0);const r=nd?f/nd:0;
    return `<div><div class="bar-h"><span style="text-transform:capitalize;font-weight:500">${esc(fmtDay(d))}</span><span class="num muted">${f}/${nd}</span></div>${meter(r,rateColor(nd?r:1))}</div>`}).join('');
  const e=ev();const steps=String(e.deroule||'').split('\n').map(l=>l.trim()).filter(Boolean).map(l=>{const m=l.match(/^(\d{1,2}[:h]\d{0,2}(?:\s*[-–]\s*\d{1,2}[:h]\d{0,2})?)\s+(.*)$/);return m?[m[1],m[2]]:['',l]});
  const der=(steps.length||e.contact||e.contactTel||e.contactInfos)?`<section class="panel deroule">${steps.length?`<ol class="steps">${steps.map(x=>`<li><span class="mono">${esc(x[0])}</span><span>${esc(x[1])}</span></li>`).join('')}</ol>`:''}${e.contact||e.contactTel||e.contactInfos?`<div class="contact"><span class="muted">${t('Contact bénévoles')}</span> ${e.contact?`<b class="sel">${esc(e.contact)}</b> `:''}<a href="contact.php?event=${encodeURIComponent(e.id)}">${t('Voir les coordonnées')}</a></div>`:''}</section>`:'';
  return `${kpis}${der}${alert}<div class="grid2">
    <section class="sec"><div class="sec-h"><h2>${t('À pourvoir en priorité')}</h2><span class="muted">${t('par ordre chronologique')}</span></div><div class="panel list">${pri}</div></section>
    <div class="sec" style="gap:22px">
      <section class="sec"><h2>${t('Par poste')}</h2><div class="panel bars">${byP}</div></section>
      <section class="sec"><h2>${t('Par jour')}</h2><div class="panel bars">${byD||'<div class="muted">—</div>'}</div></section>
    </div></div>`;
}

/* ---------- planning ---------- */
function rPlan(){
  const days=eventDays();
  if(!days.length)return `<div class="panel empty">${t('Définissez les dates de l’événement pour afficher le planning.')}</div>`;
  if(!S.day||!days.includes(S.day)){const cnt=d=>C().filter(c=>c.jour===d).length;S.day=days.reduce((b,d)=>cnt(d)>cnt(b)?d:b,days[0])}
  const chips=days.map(d=>{const n=C().filter(c=>c.jour===d).length;return `<button type="button" class="chip" aria-pressed="${d===S.day}" data-act="day" data-id="${d}">${esc(shortDay(d))}<small class="num">${n}</small></button>`}).join('');
  const cs=C().filter(c=>c.jour===S.day);const ps=P();
  let body;
  if(!ps.length){body=`<div class="panel empty">${t('Créez d’abord des postes (bar, accueil, scène…) dans l’onglet Postes & créneaux.')}</div>`}
  else if(!cs.length){body=`<div class="panel empty">${t('Aucun créneau le {d}.',{d:esc(fmtDay(S.day))})}</div>`}
  else if(S.view==='list')body=rPlanList(cs);
  else{
    const d0=dayN(S.day)*1440;
    let a=cs.length?Math.min(...cs.map(c=>span(c)[0]-d0)):600, b=cs.length?Math.max(...cs.map(c=>span(c)[1]-d0)):1440;
    const h0=Math.floor(a/60),h1=Math.max(h0+4,Math.ceil(b/60));const PX=76,W=(h1-h0)*PX;
    let ticks='';for(let h=h0;h<=h1;h++)ticks+=`<span class="tick${h%24===0&&h>0?' mid':''}" style="left:${(h-h0)*PX}px"></span>`;
    let labs='';for(let h=h0;h<h1;h++)labs+=`<span class="tick-l" style="left:${(h-h0)*PX}px">${String(h%24).padStart(2,'0')}h</span>`;
    const rows=ps.filter(p=>cs.some(c=>c.posteId===p.id)).map(p=>{
      const pc=cs.filter(c=>c.posteId===p.id);const lanes=[];const pos=[];
      pc.forEach(c=>{const s=span(c);let l=lanes.findIndex(end=>end<=s[0]);if(l<0){l=lanes.length;lanes.push(0)}lanes[l]=s[1];pos.push({c,l,s})});
      const H=Math.max(1,lanes.length)*52+8;
      const blks=pos.map(({c,l,s})=>{const n=actifs(c.id).length,bd=+c.besoin||0;const st=fillState(c);const x=(s[0]-d0)/60-h0;const wd=(s[1]-s[0])/60*PX-4;
        return `<button type="button" class="blk ${st}" data-act="cren" data-id="${esc(c.id)}" style="left:${x*PX+2}px;top:${l*52+6}px;width:${wd}px" title="${esc(p.nom+' · '+hours(c)+' · '+n+'/'+bd)}"><span class="t">${esc(hours(c))}</span><span class="f num">${n}/${bd}</span><span class="p"><i style="width:${bd?Math.min(100,n/bd*100):100}%;background:${st==='full'?'var(--ok)':st==='part'?'var(--warn)':'var(--crit)'}"></i></span></button>`}).join('');
      return `<div class="tl-row"><div class="tl-lab"><span class="dot" style="background:${pColor(p)};margin-top:4px"></span><span>${esc(p.nom)}</span></div><div class="tl-track" style="width:${W}px;min-height:${H}px">${ticks}${blks}</div></div>`}).join('');
    body=`<div class="tl-scroll"><div class="tl" style="width:${150+W}px"><div class="tl-ruler"><div class="tl-lab">${t('Poste')}</div><div class="tl-track" style="width:${W}px;height:32px">${labs}</div></div>${rows}</div></div>
    <div class="legend"><span><span class="pill ok">${t('complet')}</span></span><span><span class="pill warn">${t('partiel')}</span></span><span><span class="pill crit">${t('vide')}</span></span><span>${t('Un créneau qui commence avant 6h compte dans la nuit du jour affiché (ex. 00h–05h le 31/12 = nuit du 31 au 1er).')}</span></div>`;
  }
  const seg=`<div class="seg" role="group" aria-label="${t('Affichage')}">${[['grid',t('Grille')],['list',t('Liste')]].map(v=>`<button type="button" data-act="view" data-id="${v[0]}" aria-pressed="${S.view===v[0]}">${v[1]}</button>`).join('')}</div>`;
  return `<section class="sec"><div class="sec-h"><div class="days">${chips}</div><div class="actions">${seg}<button class="btn pri adm" type="button" data-act="cren-new" data-day="${esc(S.day)}">${t('+ Créneau')}</button></div></div>${body}</section>`;
}
/* vue liste : comme la page d'inscription, regroupée par poste (dans l'ordre de leur premier créneau) */
function rPlanList(cs){
  const groups=cs.reduce((m,c)=>{if(!m.has(c.posteId))m.set(c.posteId,[]);m.get(c.posteId).push(c);return m},new Map());
  let html='';
  groups.forEach((pcs,pid)=>{const p=poste(pid);
    const slots=pcs.map(c=>{const as=actifs(c.id);const n=as.length,b=+c.besoin||0;const st=fillState(c);
      const act=S.ro&&n<b?`<a class="btn pri sm" href="${esc(signupUrl(c.id))}">${t('S’inscrire')}</a>`:'<span></span>';
      return `<div class="slot click" data-act="cren" data-id="${esc(c.id)}"><div class="meta"><span class="t">${esc(hours(c))}</span><span class="muted num">${fmtH(dur(c))}</span><span class="pill ${st==='full'?'ok':st==='part'?'warn':'crit'} num">${n}/${b}</span></div>${act}
        ${as.length?`<div class="who-l">${as.map(a=>esc(bName(ben(a.benevoleId)))).join(', ')}</div>`:''}${c.note?`<div class="note">${esc(c.note)}</div>`:''}</div>`}).join('');
    html+=`<div class="panel pgroup"><div class="pg-h"><span class="dot" style="background:${pColor(p)}"></span><span class="pg-t">${esc(posteNom(p))}${p&&p.responsable?` <span class="pg-r">· ${t('Resp.')} ${esc(p.responsable)}</span>`:''}</span></div>${p&&p.description?`<div class="pg-d">${esc(p.description)}</div>`:''}${slots}</div>`});
  return `<div class="pgroups">${html}</div><div class="legend"><span>${t('Un créneau qui commence avant 6h compte dans la nuit du jour affiché.')}</span></div>`;
}

/* ---------- bénévoles ---------- */
function rBens(){
  const q=S.q.trim().toLowerCase();
  const list=S.benevoles.slice().sort((a,b)=>bName(a).localeCompare(bName(b),'fr')).filter(b=>!q||[b.prenom,b.nom,b.email,b.tel,b.competences].join(' ').toLowerCase().includes(q));
  const rows=list.map(b=>{const sh=shiftsOf(b.id).filter(x=>x.a.statut!=='absent');const h=hoursOf(b.id);const cf=[...conflictsOf(b.id)].some(id=>{const c=cren(id);return c&&c.eventId===S.eventId});
    const st=cf?`<span class="pill crit">${t('chevauchement')}</span>`:sh.length?`<span class="pill ok">${t('affecté')}</span>`:`<span class="pill neu">${t('disponible')}</span>`;
    return `<tr data-act="ben" data-id="${esc(b.id)}"><td><b>${esc(bName(b))}</b>${b.competences?`<div class="muted" style="font-size:12.5px">${esc(b.competences)}</div>`:''}</td>${S.ro?'':`<td class="mono hide-sm">${esc(b.tel||'—')}<div class="muted" style="font-family:var(--f-body)">${esc(b.email||'')}</div></td>`}<td class="num">${sh.length}</td><td class="num">${h?fmtH(h):'—'}</td><td class="hide-sm">${esc(b.taille||'—')}</td><td>${st}</td></tr>`}).join('');
  return `<section class="sec"><div class="sec-h"><h2>${t('Annuaire')} <span class="muted num" style="font-size:16px">(${S.benevoles.length})</span></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;flex:1;justify-content:flex-end"><label for="q" class="sr">${t('Rechercher')}</label><input id="q" class="search" type="search" placeholder="${S.ro?t('Nom, compétence…'):t('Nom, téléphone, compétence…')}" value="${esc(S.q)}">
    <button class="btn adm" type="button" data-act="csv">${t('Exporter CSV')}</button><a class="btn adm" href="export.php?format=json">${t('Sauvegarde JSON')}</a><button class="btn pri adm" type="button" data-act="ben-new">${t('+ Bénévole')}</button></div></div>
    ${list.length?`<div class="tbl-wrap"><table><thead><tr><th>${t('Bénévole')}</th>${S.ro?'':`<th class="hide-sm">${t('Contact')}</th>`}<th>${t('Créneaux')}</th><th>${t('Heures')}</th><th class="hide-sm">${t('T-shirt')}</th><th>${t('État')}</th></tr></thead><tbody>${rows}</tbody></table></div>`:`<div class="panel empty">${S.benevoles.length?t('Aucun bénévole ne correspond à la recherche.'):t('Aucun bénévole. Ajoutez votre équipe.')}</div>`}
    <p class="muted" style="margin:0">${t('Créneaux et heures comptés pour l’événement sélectionné ; les absents ne sont pas comptés.')}</p></section>`;
}

/* ---------- postes ---------- */
function rPostes(){
  const cards=P().map(p=>{const pc=C().filter(c=>c.posteId===p.id);
    const rows=pc.map(c=>{const n=actifs(c.id).length,b=+c.besoin||0;const st=fillState(c);return `<div class="row click" data-act="cren" data-id="${esc(c.id)}"><span><b style="text-transform:capitalize;font-weight:500">${esc(shortDay(c.jour))}</b> <span class="mono muted">${esc(hours(c))}</span></span><span class="muted num">${fmtH(dur(c))}</span><span class="pill ${st==='full'?'ok':st==='part'?'warn':'crit'} num">${n}/${b}</span></div>`}).join('');
    return `<article class="panel"><div class="pc-h"><div><h3 class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(p.nom)}</h3>${p.responsable?`<div class="muted" style="font-size:13px;margin-top:3px">${t('Resp.')} ${esc(p.responsable)}</div>`:''}</div><button class="btn sm adm" type="button" data-act="poste-edit" data-id="${esc(p.id)}">${t('Modifier')}</button></div>
    ${p.description?`<div class="pc-d">${esc(p.description)}</div>`:''}
    <div class="pc-c list">${rows||`<div class="empty" style="padding:14px">${t('Aucun créneau.')}</div>`}</div>
    <div class="pc-f adm"><button class="btn sm" type="button" data-act="cren-new" data-poste="${esc(p.id)}">${t('+ Créneau')}</button></div></article>`}).join('');
  return `<section class="sec"><div class="sec-h"><h2>${t('Postes & créneaux')}</h2><button class="btn pri adm" type="button" data-act="poste-new">${t('+ Poste')}</button></div>
  ${cards?`<div class="postes">${cards}</div>`:`<div class="panel empty">${t('Aucun poste. Créez par exemple Bar, Accueil billetterie, Catering artistes, Parking…')}</div>`}</section>`;
}

/* ---------- tiroir ---------- */
function openDr(kind,id,extra,form){DR.kind=kind;DR.id=id;DR.extra=extra||null;DR.form=!!form;$('#drawer').hidden=false;$('#scrim').hidden=false;renderDrawer();const f=$('#drawer input,#drawer select');if(form&&f)f.focus()}
function closeDr(){DR.kind=null;$('#drawer').hidden=true;$('#scrim').hidden=true}
function head(title,sub){return `<div class="dr-h"><div><h2>${title}</h2>${sub?`<div class="muted" style="margin-top:3px">${sub}</div>`:''}</div><button class="x" type="button" data-act="close" aria-label="${t('Fermer')}">×</button></div>`}
function renderDrawer(){
  const d=$('#drawer');const k=DR.kind;
  if(k==='cren'){const c=cren(DR.id);if(!c){closeDr();return}d.innerHTML=drCren(c)}
  else if(k==='ben'){const b=ben(DR.id);if(!b){closeDr();return}d.innerHTML=drBen(b)}
  else if(k==='form')d.innerHTML=drForm();
  else if(k==='login')d.innerHTML=head(t('Espace organisateurs'),t('Un mot de passe partagé pour toute l’équipe d’organisation.'))+
    `<form class="dr-b" id="loginFrm">${CFG.authEnabled?`<div class="field"><label for="pw">${t('Mot de passe')}</label><input id="pw" name="password" type="password" autocomplete="current-password" required></div><div class="actions"><button class="btn pri" type="submit">${t('Se connecter')}</button></div>`:`<div class="notice">${t('Aucun mot de passe administrateur n’est configuré. Renseignez admin_password_hash dans config.php.')}</div>`}</form>`;
}
function drCren(c){
  const p=poste(c.posteId);const as=affsOf(c.id);const n=actifs(c.id).length;const b=+c.besoin||0;
  const who=as.map(a=>{const bb=ben(a.benevoleId);const cf=conflictsOf(a.benevoleId).has(c.id)&&a.statut!=='absent';
    return `<div class="who"><span><b>${esc(bName(bb))}</b>${bb&&bb.tel?` <span class="mono muted">${esc(bb.tel)}</span>`:''}${cf?` <span class="pill crit">${t('chevauchement')}</span>`:''}</span>
    ${S.ro?`<span class="pill ${a.statut==='present'?'ok':a.statut==='absent'?'crit':'neu'}">${statutLab(a)}</span><span></span>`:`<select data-act="statut" data-id="${esc(a.id)}" aria-label="${t('Statut')}">${STATUTS.map(s=>`<option value="${s[0]}"${(a.statut||'prevu')===s[0]?' selected':''}>${s[1]}</option>`).join('')}</select>
    <button class="btn sm" type="button" data-act="unassign" data-id="${esc(a.id)}">${t('Retirer')}</button>`}</div>`}).join('');
  const inIds=new Set(as.map(a=>a.benevoleId));
  const cand=S.benevoles.filter(x=>!inIds.has(x.id)).map(x=>({x,busy:busyFor(x.id,c),h:hoursOf(x.id)})).sort((a,b)=>(a.busy-b.busy)||(a.h-b.h)||bName(a.x).localeCompare(bName(b.x),'fr'));
  const opts=cand.map(o=>`<option value="${esc(o.x.id)}"${o.busy?' disabled':''}>${esc(bName(o.x))} · ${o.busy?t('déjà occupé'):fmtH(o.h)}</option>`).join('');
  return head(`<span class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(posteNom(p))}</span>`,`<span style="text-transform:capitalize">${esc(fmtDay(c.jour))}</span> · <span class="mono">${esc(hours(c))}</span> · ${fmtH(dur(c))}`)+
  `<div class="dr-b">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span class="pill ${fillState(c)==='full'?'ok':n?'warn':'crit'} num">${t('{n}/{b} bénévoles',{n,b})}</span>${c.note?`<span class="muted">${esc(c.note)}</span>`:''}</div>
    <div><h3 style="margin-bottom:6px">${t('Équipe')}</h3>${who||`<div class="muted">${t('Personne n’est encore affecté.')}</div>`}</div>
    ${S.ro?(n<b?`<a class="btn pri" style="align-self:flex-start" href="${esc(signupUrl(c.id))}">${tn(b-n,'S’inscrire sur ce créneau ({n} place)','S’inscrire sur ce créneau ({n} places)')}</a>`:`<span class="muted">${t('Ce créneau est complet.')}</span>`):`<div class="field"><label for="addBen">${t('Affecter un bénévole')}</label>
      ${cand.length?`<div style="display:flex;gap:8px"><select id="addBen"><option value="">${t('Choisir…')}</option>${opts}</select><button class="btn pri" type="button" data-act="assign">${t('Affecter')}</button></div><span class="muted" style="font-size:12.5px">${t('Triés par heures déjà planifiées (les moins chargés d’abord). Les bénévoles déjà occupés sur cette plage sont grisés.')}</span>`:`<span class="muted">${t('Tous les bénévoles de l’annuaire sont déjà sur ce créneau.')}</span>`}
    </div>`}
    <div class="actions adm"><button class="btn" type="button" data-act="cren-edit" data-id="${esc(c.id)}">${t('Modifier le créneau')}</button><button class="btn danger" type="button" data-act="cren-del" data-id="${esc(c.id)}">${t('Supprimer le créneau')}</button></div>
  </div>`;
}
function drBen(b){
  const sh=shiftsOf(b.id);const cf=conflictsOf(b.id);
  const rows=sh.map(({a,c})=>{const p=poste(c.posteId);return `<div class="row click" data-act="cren" data-id="${esc(c.id)}" style="padding:8px 0;grid-template-columns:auto 1fr auto"><div class="when"><b>${esc(shortDay(c.jour))}</b><span class="mono muted">${esc(hours(c))}</span></div><span class="pname"><span class="dot" style="background:${pColor(p)}"></span>${esc(p?p.nom:'—')}</span>${cf.has(c.id)&&a.statut!=='absent'?`<span class="pill crit">${t('chevauchement')}</span>`:`<span class="pill ${a.statut==='present'?'ok':a.statut==='absent'?'crit':'neu'}">${statutLab(a)}</span>`}</div>`}).join('');
  return head(esc(bName(b)),b.competences?esc(b.competences):'')+
  `<div class="dr-b">
    <dl class="kv">${S.ro?'':`<dt>${t('Téléphone')}</dt><dd class="mono">${esc(b.tel||'—')}</dd><dt>${t('E-mail')}</dt><dd>${esc(b.email||'—')}</dd>`}<dt>${t('T-shirt')}</dt><dd>${esc(b.taille||'—')}</dd><dt>${t('Heures')}</dt><dd class="num">${t('{h} sur cet événement',{h:fmtH(hoursOf(b.id))})}</dd>${b.notes?`<dt>${t('Notes')}</dt><dd>${esc(b.notes)}</dd>`:''}</dl>
    <div><h3 style="margin-bottom:4px">${t('Créneaux sur {e}',{e:esc(ev()?ev().nom:t('l’événement'))})}</h3><div class="list">${rows||`<div class="muted">${t('Aucun créneau pour l’instant. Ouvrez un créneau dans le Planning pour l’affecter.')}</div>`}</div></div>
    <div class="actions"><button class="btn adm" type="button" data-act="ben-edit" data-id="${esc(b.id)}">${t('Modifier la fiche')}</button><button class="btn" type="button" data-act="copy" data-id="${esc(b.id)}">${t('Copier son planning')}</button></div>
  </div>`;
}

/* ---------- formulaires ---------- */
const FORMS={
  event:{nw:t('Nouvel événement'),edit:t('Modifier l’événement'),created:t('Événement créé'),col:'events',fields:[{k:'nom',l:t('Nom de l’événement'),req:1,ph:'Festival des Remparts 2027'},{k:'lieu',l:t('Lieu'),ph:t('Ville, site')},[{k:'debut',l:t('Premier jour'),type:'date',req:1},{k:'fin',l:t('Dernier jour'),type:'date',req:1}],{k:'imageUrl',l:t('Image / logo (adresse https)'),type:'url',ph:'https://…/logo.png'},[{k:'contact',l:t('E-mail de contact'),type:'email',ph:t('adresse e-mail')},{k:'contactTel',l:t('Téléphone de contact'),type:'tel'}],{k:'contactInfos',l:t('Autres informations de contact (une par ligne : adresse, site web, réseaux sociaux…)'),type:'textarea'},{k:'deroule',l:t('Déroulé (une étape par ligne, ex. « 20:00 Ouverture des portes »)'),type:'textarea'}]},
  poste:{nw:t('Nouveau poste'),edit:t('Modifier le poste'),created:t('Poste ajouté'),col:'postes',fields:[{k:'nom',l:t('Nom du poste'),req:1,ph:t('Bar principal')},{k:'responsable',l:t('Responsable'),ph:t('Prénom Nom')},{k:'couleur',l:t('Couleur'),type:'color'},{k:'description',l:t('Consignes'),type:'textarea',ph:t('Tenue, matériel, point de rendez-vous…')}]},
  cren:{nw:t('Nouveau créneau'),edit:t('Modifier le créneau'),created:t('Créneau ajouté'),col:'creneaux',fields:[{k:'posteId',l:t('Poste'),type:'poste',req:1},{k:'jour',l:t('Jour (nuit comprise jusqu’à 6h)'),type:'day',req:1},[{k:'debut',l:t('Début'),type:'time',req:1},{k:'fin',l:t('Fin'),type:'time',req:1}],{k:'besoin',l:t('Bénévoles nécessaires'),type:'number',req:1},{k:'note',l:t('Note'),ph:t('Ex. : briefing 30 min avant')}]},
  ben:{nw:t('Nouveau bénévole'),edit:t('Modifier la fiche'),created:t('Bénévole ajouté'),col:'benevoles',fields:[[{k:'prenom',l:t('Prénom'),req:1},{k:'nom',l:t('Nom'),req:1}],[{k:'tel',l:t('Téléphone'),type:'tel'},{k:'email',l:t('E-mail'),type:'email'}],{note:t('Téléphone et e-mail sont facultatifs mais recommandés : c’est plus simple pour vous prévenir d’un changement. Ils ne sont visibles que par les organisateurs.')},{k:'taille',l:t('Taille de T-shirt'),type:'size'},{k:'competences',l:t('Compétences'),ph:t('Permis B, PSC1, anglais, caisse…')},{k:'notes',l:t('Notes'),type:'textarea',ph:t('Disponibilités, contraintes, allergies…')}]}
};
const COLOR_NAMES=[t('Bleu'),t('Corail'),t('Vert'),t('Violet'),t('Moutarde'),t('Rose'),t('Cyan'),t('Olive')];
function fieldHtml(f,v){
  const id='f_'+f.k;const val=v==null?'':v;const req=f.req?' required':'';
  let inp;
  if(f.type==='textarea')inp=`<textarea id="${id}" name="${f.k}" placeholder="${esc(f.ph||'')}">${esc(val)}</textarea>`;
  else if(f.type==='poste')inp=`<select id="${id}" name="${f.k}"${req}>${P().map(p=>`<option value="${esc(p.id)}"${p.id===val?' selected':''}>${esc(p.nom)}</option>`).join('')}</select>`;
  else if(f.type==='day')inp=`<select id="${id}" name="${f.k}"${req}>${eventDays().map(d=>`<option value="${d}"${d===val?' selected':''}>${esc(fmtDay(d))}</option>`).join('')}</select>`;
  else if(f.type==='size')inp=`<select id="${id}" name="${f.k}"><option value="">—</option>${['XS','S','M','L','XL','XXL'].map(s=>`<option${s===val?' selected':''}>${s}</option>`).join('')}</select>`;
  else if(f.type==='color')inp=`<select id="${id}" name="${f.k}">${COLOR_NAMES.map((n,i)=>`<option value="${i}"${+val===i?' selected':''}>${n}</option>`).join('')}</select>`;
  else inp=`<input id="${id}" name="${f.k}" type="${f.type||'text'}" value="${esc(val)}" placeholder="${esc(f.ph||'')}"${f.type==='number'?' min="1" step="1"':''}${req}>`;
  return `<div class="field"><label for="${id}">${esc(f.l)}${f.req?' *':''}</label>${inp}</div>`;
}
function drForm(){
  const {type,id,pre}=DR.extra;const F=FORMS[type];const src={events:S.events,postes:S.postes,creneaux:S.creneaux,benevoles:S.benevoles}[F.col];
  const cur=id?src.find(x=>x.id===id):null;const v=Object.assign({},pre||{},cur||{});
  const body=F.fields.map(f=>f.note?`<p class="muted" style="margin:-8px 0 0;font-size:12.5px">${esc(f.note)}</p>`:Array.isArray(f)?`<div class="frow">${f.map(g=>fieldHtml(g,v[g.k])).join('')}</div>`:fieldHtml(f,v[f.k])).join('');
  const blocked=type==='cren'&&!P().length;
  const cascade={event:t('La suppression retire aussi ses postes, créneaux et affectations.'),poste:t('La suppression retire aussi ses créneaux et leurs affectations.'),cren:t('La suppression retire aussi les affectations de ce créneau.')}[type];
  return head(cur?F.edit:F.nw,'')+
  `<form class="dr-b" id="frm" novalidate>${blocked?`<div class="notice">${t('Créez d’abord un poste.')}</div>`:body}
   <div class="actions"><button class="btn pri" type="submit"${blocked?' disabled':''}>${cur?t('Enregistrer'):t('Créer')}</button>${cur?`<button class="btn danger" type="button" data-act="del">${t('Supprimer')}</button>`:''}</div>
   ${cur&&cascade?`<p class="muted" style="margin:0;font-size:12.5px">${cascade}</p>`:''}
  </form>`;
}
function openForm(type,id,pre){openDr('form',null,{type,id,pre},true)}
async function submitForm(fd){
  const {type,id}=DR.extra;const F=FORMS[type];const data={};
  for(const [k,v] of fd.entries())data[k]=typeof v==='string'?v.trim():v;
  const flat=F.fields.flat();
  const miss=flat.filter(f=>f.req&&!data[f.k]);
  if(miss.length){toast(t('À compléter : {l}',{l:miss.map(f=>f.l).join(', ')}));return}
  if(type==='cren'){data.besoin=Math.max(1,parseInt(data.besoin,10)||1);if(data.debut===data.fin){toast(t('Le début et la fin doivent être différents.'));return}}
  if(type==='poste')data.couleur=+data.couleur||0;
  if(type==='event'&&data.fin<data.debut){toast(t('Le dernier jour doit suivre le premier.'));return}
  if(type!=='event'&&type!=='ben')data.eventId=S.eventId;
  let newEv=null;
  const ok=await w(async()=>{
    if(id)await update(F.col,id,data);
    else{const r=await create(F.col,data);if(type==='event')newEv=r.id}
  },id?t('Modifications enregistrées'):F.created);
  if(ok&&newEv){location.href=evUrl(newEv);return}
  if(ok){closeDr();render()}
}
/* les suppressions en cascade (créneaux, affectations…) sont faites par la base */
/* suppression en deux clics : le 1er clic arme le bouton (libellé de confirmation), le 2e exécute */
function armed(el,label){
  if(el.classList.contains('armed'))return true;
  const lab=el.textContent;el.classList.add('armed');el.textContent=label;
  setTimeout(()=>{if(el.isConnected&&el.classList.contains('armed')){el.classList.remove('armed');el.textContent=lab}},5000);
  return false;
}
async function deleteCren(id){
  if(await w(()=>remove('creneaux',id),t('Créneau supprimé'))){closeDr();render()}
}
async function deleteCurrent(){
  const {type,id}=DR.extra;const F=FORMS[type];
  const ok=await w(()=>remove(F.col,id),t('Supprimé'));
  if(ok&&type==='event'){location.href=evUrl('');return}
  if(ok){closeDr();render()}
}

/* ---------- export ---------- */
function exportCsv(){if(S.eventId)location.href='export.php?format=csv&event='+encodeURIComponent(S.eventId)}
function planText(b){const e=ev();return `${bName(b)} – ${e?e.nom:''}\n`+shiftsOf(b.id).filter(x=>x.a.statut!=='absent').map(({c})=>{const p=poste(c.posteId);return `• ${shortDay(c.jour)} ${hours(c)} : ${p?p.nom:''}${p&&p.responsable?' ('+t('resp.')+' '+p.responsable+')':''}`}).join('\n')}

/* ---------- événements UI ---------- */
document.addEventListener('click',async ev0=>{
  if(ev0.target.closest('a[href]:not([data-act])'))return; /* vrai lien (ex. S'inscrire dans une ligne cliquable) */
  const el=ev0.target.closest('[data-act],.tab');if(!el)return;
  if(el.classList.contains('tab')){S.tab=el.dataset.tab;ls.set('rb.tab',S.tab);render();return}
  const act=el.dataset.act,id=el.dataset.id;
  if(el.tagName==='A')ev0.preventDefault();
  switch(act){
    case 'close':closeDr();break;
    case 'day':S.day=id;render();break;
    case 'view':S.view=id;ls.set('rb.view',id);render();break;
    case 'cren':openDr('cren',id);break;
    case 'ben':openDr('ben',id);break;
    case 'ev-new':openForm('event');break;
    case 'poste-new':openForm('poste');break;
    case 'poste-edit':openForm('poste',id);break;
    case 'cren-new':openForm('cren',null,{posteId:el.dataset.poste||(P()[0]&&P()[0].id),jour:el.dataset.day||S.day||eventDays()[0],debut:'18:00',fin:'22:00',besoin:2});break;
    case 'cren-edit':openForm('cren',id);break;
    case 'cren-del':{const n=affsOf(id).length;if(armed(el,n?tn(n,'Confirmer (retire {n} affectation)','Confirmer (retire {n} affectations)'):t('Confirmer')))deleteCren(id);break}
    case 'ben-new':openForm('ben');break;
    case 'ben-edit':openForm('ben',id);break;
    case 'csv':exportCsv();break;
    case 'copy':{const b=ben(id);try{await navigator.clipboard.writeText(planText(b));toast(t('Planning copié, prêt à envoyer par SMS ou e-mail.'))}catch(e){toast(t('Copie impossible dans cette vue.'))}break}
    case 'del':if(armed(el,t('Confirmer la suppression')))deleteCurrent();break;
    case 'assign':{const sel=$('#addBen');const bid=sel&&sel.value;if(!bid){toast(t('Choisissez un bénévole.'));break}const c=cren(DR.id);
      if(await w(()=>create('affs',{eventId:c.eventId,creneauId:c.id,benevoleId:bid,statut:'prevu'}),t('{b} affecté',{b:bName(ben(bid))})))render();break}
    case 'unassign':if(await w(()=>remove('affs',id),t('Retiré du créneau')))render();break;
  }
});
document.addEventListener('change',async e=>{
  const el=e.target;
  if(el.id==='evSel'){location.href=evUrl(el.value)}
  else if(el.dataset&&el.dataset.act==='statut'){if(await w(()=>update('affs',el.dataset.id,{statut:el.value}),t('Statut mis à jour')))render()}
});
document.addEventListener('input',e=>{if(e.target.id==='q'){S.q=e.target.value;render.focusQ=true;render();render.focusQ=false}});
document.addEventListener('submit',async e=>{
  if(e.target.id==='frm'){e.preventDefault();submitForm(new FormData(e.target))}
  else if(e.target.id==='loginFrm'){e.preventDefault();
    try{await api({action:'login',password:e.target.password.value});location.reload()}
    catch(err){toast((err&&err.message)||t('Connexion impossible.'))}}
});
$('#login').addEventListener('click',()=>openDr('login',null,null,true));
$('#logout').addEventListener('click',async()=>{try{await api({action:'logout'})}catch(e){}location.reload()});
$('#scrim').addEventListener('click',closeDr);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&DR.kind)closeDr()});
$('#evNew').addEventListener('click',()=>openForm('event'));
$('#evEdit').addEventListener('click',()=>{if(S.eventId)openForm('event',S.eventId)});

/* ---------- démarrage ---------- */
/* remplace le temps réel du prototype : rechargement complet après chaque écriture et toutes les 15 s */
async function load(){
  const j=await api();
  const snap=JSON.stringify([j.events,j.postes,j.creneaux,j.benevoles,j.affectations]);
  const changed=snap!==lastSnap;lastSnap=snap;
  if(changed){S.events=j.events;S.postes=j.postes;S.creneaux=j.creneaux;S.benevoles=j.benevoles;S.affs=j.affectations}
  if(!S.ro&&!j.admin){location.reload()} /* session admin expirée */
  db=true;return changed;
}
async function poll(){
  if(document.hidden||DR.form)return;
  const typing=document.activeElement&&document.activeElement.id==='q';
  const pick=$('#addBen');const picked=pick&&pick.value;
  try{if(await load()){render.focusQ=typing;render();render.focusQ=false;const p2=$('#addBen');if(picked&&p2)p2.value=picked}}
  catch(e){if(e&&(e.code==='auth'||e.code==='csrf'))location.reload()}
}
async function boot(){
  S.ro=!CFG.admin;
  $('#logout').hidden=S.ro;$('#login').hidden=!S.ro;$('#signupLink').hidden=!S.ro;
  if(CFG.setupError){render();return}
  try{await load()}catch(e){render.noDb=true;toast((e&&e.message)||t('Chargement impossible.'))}
  render();
  setInterval(poll,REFRESH_MS);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});
}
boot();
})();
</script>
</body>
</html>
