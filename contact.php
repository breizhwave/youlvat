<?php
// Page publique : contact de l'événement. Texte libre (modifiable par les organisateurs avec l'éditeur intégré,
// une version par langue) + e-mail, téléphone et autres informations.
require_once __DIR__ . '/lib/auth.php';
start_session();
$setupError = null;
try { db(); } catch (Throwable $e) { $setupError = $e instanceof DbSetupError ? $e->getMessage() : 'Connexion à la base impossible.'; }
$boot = ['csrf' => csrf_token(), 'admin' => is_admin(), 'setupError' => $setupError];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Contact</title>
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
        </div>
        <div class="brand-s" id="evMeta" data-i18n="Chargement…">Chargement…</div>
      </div>
    </div>
    <div class="evpick">
      <a class="btn pri sm" id="signupLink" href="inscription.php" data-i18n="S’inscrire">S’inscrire</a>
      <a class="btn band-btn sm" id="planLink" href="index.php" data-i18n="Voir le planning">Voir le planning</a>
      <div class="langsw" role="group" aria-label="Yezh / Langue"></div>
    </div>
  </div>
</header>
<main class="wrap" id="main"><div class="empty" data-i18n="Chargement…">Chargement…</div></main>
<div id="toast" role="status" hidden></div>

<script>
window.REGIE=<?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
(function(){
const {t}=I18N;
I18N.applyStatic();
const CFG=window.REGIE||{};
const S={events:[],eventId:new URLSearchParams(location.search).get('event')||null,loaded:false,editing:false};
const $=s=>document.querySelector(s);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtDay=I18N.fmtDay;
const ev=()=>S.events.find(e=>e.id===S.eventId);
const evImgUrl=e=>e&&/^https:\/\//.test(e.imageUrl||'')?e.imageUrl:'';
const isPast=e=>(e.fin||e.debut)<new Date().toISOString().slice(0,10);
const evWhen=e=>[e.lieu,e.debut?(fmtDay(e.debut,{day:'numeric',month:'long'})+(e.fin&&e.fin!==e.debut?' → '+fmtDay(e.fin,{day:'numeric',month:'long',year:'numeric'}):'')):''].filter(Boolean).join(' · ');
const q=id=>id?'?event='+encodeURIComponent(id):'';
const isEmail=s=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s||'');
/* texte de la page : une colonne par langue ; en breton ou en anglais, repli sur le français si vide */
const HTML_KEY={br:'contactHtmlBr',en:'contactHtmlEn'}[I18N.lang]||'contactHtml';
const pageHtml=e=>e[HTML_KEY]||e.contactHtml||'';

function toast(msg){const el=$('#toast');el.textContent=msg;el.hidden=false;clearTimeout(toast._t);toast._t=setTimeout(()=>{el.hidden=true},3000)}
async function api(body){
  const opt=body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CFG.csrf},body:JSON.stringify(body)}:{};
  let r,j;
  try{r=await fetch(body?'api.php':'api.php?action=all',Object.assign({credentials:'same-origin',cache:'no-store'},opt));j=await r.json()}
  catch(e){throw {message:t('Serveur injoignable. Vérifiez la connexion.')}}
  if(!r.ok||!j.ok)throw {code:j&&j.error,message:j&&j.message&&t(j.message)};
  return j;
}
async function load(){const j=await api();S.events=j.events;S.loaded=true}

/* affichage du texte riche : même liste blanche que le serveur (lib/html.php), par sécurité */
const RICH_TAGS=['P','BR','STRONG','B','EM','I','U','A','UL','OL','LI','H3','H4','BLOCKQUOTE'];
function safeHtml(html){
  const doc=new DOMParser().parseFromString('<div>'+html+'</div>','text/html');
  const walk=node=>{[...node.childNodes].forEach(ch=>{
    if(ch.nodeType===3)return;
    if(ch.nodeType!==1){ch.remove();return}
    if(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','TEMPLATE','FORM','SVG'].includes(ch.tagName)){ch.remove();return}
    walk(ch);
    if(!RICH_TAGS.includes(ch.tagName)){ch.replaceWith(...ch.childNodes);return}
    [...ch.attributes].forEach(a=>{if(!(ch.tagName==='A'&&a.name==='href'))ch.removeAttribute(a.name)});
    if(ch.tagName==='A'){if(/^(https?:\/\/|mailto:|tel:)/i.test(ch.getAttribute('href')||'')){ch.target='_blank';ch.rel='noopener'}else ch.removeAttribute('href')}
  })};
  const root=doc.body.firstChild;walk(root);return root.innerHTML;
}
/* une ligne de texte libre : liens web et e-mails rendus cliquables, le reste échappé */
function linkify(line){
  return esc(line)
    .replace(/\bhttps?:\/\/[^\s<]+/g,u=>`<a href="${u}" target="_blank" rel="noopener">${u.replace(/^https?:\/\//,'')}</a>`)
    .replace(/(^|[\s(])([^\s@<>()]+@[^\s@<>()]+\.[a-z]{2,})/gi,(m,pre,mail)=>`${pre}<a href="mailto:${mail}">${mail}</a>`);
}

function renderHeader(){
  const e=ev();const evs=S.events.slice().sort((a,b)=>isPast(a)-isPast(b)||String(a.debut).localeCompare(b.debut));
  const sel=$('#evSel');
  sel.innerHTML=`<option value="">${evs.length?t('Tous les événements'):t('Aucun événement')}</option>`+evs.map(x=>`<option value="${esc(x.id)}"${x.id===S.eventId?' selected':''}>${esc(x.nom)}</option>`).join('');
  sel.disabled=!evs.length;
  const img=$('#evImg');const iu=evImgUrl(e);img.hidden=!iu;if(iu&&img.getAttribute('src')!==iu)img.src=iu;
  $('#evMeta').textContent=e?evWhen(e):t('Choisissez un événement');
  $('#planLink').href='index.php'+q(e&&e.id);
  $('#signupLink').href='inscription.php'+q(e&&e.id);$('#signupLink').hidden=!!(e&&isPast(e));
  document.title=(e?e.nom+' · ':'')+t('Contact');
}

function rList(){
  const evs=S.events.slice().sort((a,b)=>isPast(a)-isPast(b)||String(a.debut).localeCompare(b.debut));
  const lost=S.eventId?`<div class="notice">${t('Cet événement n’existe pas ou a été supprimé.')}</div>`:'';
  const cards=evs.map(e=>{const iu=evImgUrl(e);
    return `<article class="panel evcard${isPast(e)?' past':''}"><a class="evcard-h" href="${q(e.id)}">${iu?`<img class="evcard-img" src="${esc(iu)}" alt="" loading="lazy">`:''}<span><h3>${esc(e.nom)}</h3><span class="muted" style="font-size:13px">${esc(evWhen(e))}</span></span></a>
      <div class="actions" style="justify-content:flex-start"><a class="btn pri" href="${q(e.id)}">${t('Voir les coordonnées')}</a></div></article>`}).join('');
  return `${lost}<section class="sec"><h2>${t('Événements')}</h2>${cards?`<div class="evlist">${cards}</div>`:`<div class="panel empty">${t('Aucun événement')}</div>`}</section>`;
}

function rView(e){
  const rows=[];
  if(e.contact)rows.push([t('E-mail'),isEmail(e.contact)?`<a href="mailto:${esc(e.contact)}">${esc(e.contact)}</a>`:esc(e.contact)]);
  if(e.contactTel)rows.push([t('Téléphone'),`<a href="tel:${esc(String(e.contactTel).replace(/[^0-9+]/g,''))}">${esc(e.contactTel)}</a>`]);
  const infos=String(e.contactInfos||'').split('\n').map(l=>l.trim()).filter(Boolean);
  if(infos.length)rows.push([t('Autres informations'),infos.map(l=>`<div>${linkify(l)}</div>`).join('')]);
  const html=pageHtml(e);
  return `<section class="panel contact-page">
    <div class="sec-h"><h2>${t('Nous contacter')}</h2><button class="btn sm adm" type="button" data-act="edit">${t('Modifier la page')}</button></div>
    <div class="rich">${html?safeHtml(html):`<p class="muted">${t('Une question sur le bénévolat ? Écrivez-nous ou appelez-nous.')}</p>`}</div>
    ${rows.length?`<dl class="contact-list">${rows.map(r=>`<dt>${r[0]}</dt><dd>${r[1]}</dd>`).join('')}</dl>`:`<div class="empty" style="padding:14px 0">${t('Aucune information de contact pour l’instant.')}</div>`}
  </section>`;
}

/* ---------- éditeur (organisateurs) ---------- */
const TOOLS=[['bold','B',t('Gras')],['italic','I',t('Italique')],['h3','H',t('Titre')],['insertUnorderedList','•',t('Liste à puces')],['insertOrderedList','1.',t('Liste numérotée')],['link','🔗',t('Lien')],['unlink','⛓̸',t('Retirer le lien')],['removeFormat','⌫',t('Effacer la mise en forme')]];
function rEdit(e){
  const html=e[HTML_KEY]||'';
  return `<section class="panel contact-page">
    <h2>${t('Nous contacter')}</h2>
    <div class="field"><label id="rteLab">${t('Texte de la page en {l}',{l:t({br:'breton',en:'anglais'}[I18N.lang]||'français')})}</label>
      <div class="rte-bar" role="toolbar" aria-label="${t('Mise en forme')}">${TOOLS.map(([c,l,tt])=>`<button type="button" data-cmd="${c}" title="${esc(tt)}" aria-label="${esc(tt)}">${l}</button>`).join('')}</div>
      <div class="rte" id="rte" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="rteLab">${html?safeHtml(html):`<p>${t('Une question sur le bénévolat ? Écrivez-nous ou appelez-nous.')}</p>`}</div>
      <span class="muted" style="font-size:12.5px">${t('Pour modifier une autre version, changez de langue en haut de page (après avoir enregistré).')}</span>
    </div>
    <h3 style="margin-top:6px">${t('Coordonnées')}</h3>
    <div class="frow">
      <div class="field"><label for="cEmail">${t('E-mail de contact')}</label><input id="cEmail" type="email" value="${esc(e.contact||'')}" placeholder="${t('adresse e-mail')}"></div>
      <div class="field"><label for="cTel">${t('Téléphone de contact')}</label><input id="cTel" type="tel" value="${esc(e.contactTel||'')}"></div>
    </div>
    <div class="field"><label for="cInfos">${t('Autres informations de contact (une par ligne : adresse, site web, réseaux sociaux…)')}</label><textarea id="cInfos" rows="4">${esc(e.contactInfos||'')}</textarea></div>
    <div class="actions" style="justify-content:flex-start"><button class="btn pri" type="button" data-act="save">${t('Enregistrer')}</button><button class="btn" type="button" data-act="cancel">${t('Annuler')}</button></div>
  </section>`;
}
function runCmd(cmd){
  const rte=$('#rte');if(!rte)return;rte.focus();
  if(cmd==='h3'){const cur=(document.queryCommandValue('formatBlock')||'').toLowerCase();document.execCommand('formatBlock',false,cur==='h3'?'p':'h3');return}
  if(cmd==='link'){
    const url=prompt(t('Adresse du lien (https://…, mailto:… ou tel:…)'),'https://');
    if(!url)return;
    if(!/^(https?:\/\/|mailto:|tel:)\S+$/i.test(url.trim())){toast(t('Adresse de lien invalide : utilisez https://, mailto: ou tel:'));return}
    document.execCommand('createLink',false,url.trim());return;
  }
  document.execCommand(cmd,false,null);
}
async function save(){
  const e=ev();const rte=$('#rte');
  const data={[HTML_KEY]:rte.innerHTML,contact:$('#cEmail').value.trim(),contactTel:$('#cTel').value.trim(),contactInfos:$('#cInfos').value.trim()};
  const btn=$('[data-act="save"]');btn.disabled=true;
  try{
    await api({action:'update',table:'events',id:e.id,data});
    await load();S.editing=false;render();toast(t('Modifications enregistrées'));
  }catch(err){btn.disabled=false;toast((err&&err.message)||t('Enregistrement impossible pour le moment. Réessayez.'))}
}

function render(){
  const m=$('#main');
  if(CFG.setupError){m.innerHTML=`<div class="notice">${esc(t(CFG.setupError))}</div>`;return}
  if(!S.loaded){m.innerHTML=`<div class="notice">${t('Impossible de charger les données. Vérifiez la connexion puis rechargez la page.')}</div>`;return}
  renderHeader();
  const e=ev();
  if(!e){m.innerHTML=rList();return}
  m.innerHTML=S.editing&&CFG.admin?rEdit(e):rView(e);
}

document.addEventListener('mousedown',e=>{if(e.target.closest('[data-cmd]'))e.preventDefault()}); /* garde la sélection dans l'éditeur */
document.addEventListener('click',e=>{
  const c=e.target.closest('[data-cmd]');if(c){runCmd(c.dataset.cmd);return}
  const a=e.target.closest('[data-act]');if(!a)return;
  if(a.dataset.act==='edit'){S.editing=true;render();const r=$('#rte');if(r)r.focus()}
  else if(a.dataset.act==='cancel'){S.editing=false;render()}
  else if(a.dataset.act==='save')save();
});
/* collage : texte brut uniquement (évite la mise en forme parasite de Word, des pages web…) */
document.addEventListener('paste',e=>{
  if(!e.target.closest||!e.target.closest('#rte'))return;
  e.preventDefault();
  const txt=(e.clipboardData||window.clipboardData).getData('text/plain');
  document.execCommand('insertText',false,txt);
});
document.addEventListener('change',e=>{
  if(e.target.id==='evSel')location.href=location.pathname+q(e.target.value);
});
window.addEventListener('beforeunload',e=>{if(S.editing){e.preventDefault();e.returnValue=''}});

async function boot(){
  if(CFG.setupError){render();return}
  try{await load()}catch(e){}
  render();
}
boot();
})();
</script>
</body>
</html>
