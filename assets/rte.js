/* Éditeur de texte riche commun (page contact, encadré d'appel aux bénévoles) :
   contenteditable + execCommand, sans bibliothèque. Le serveur nettoie le HTML (lib/html.php) ;
   safeHtml() applique la même liste blanche à l'affichage. À charger après i18n.js. */
(function(){
const {t}=I18N;
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

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

const TOOLS=()=>[['bold','B',t('Gras')],['italic','I',t('Italique')],['h3','H',t('Titre')],['insertUnorderedList','•',t('Liste à puces')],['insertOrderedList','1.',t('Liste numérotée')],['link','🔗',t('Lien')],['unlink','⛓̸',t('Retirer le lien')],['removeFormat','⌫',t('Effacer la mise en forme')]];
/* barre d'outils + zone éditable ; opts : {id, html, label (id du libellé), placeholder, tools (liste de commandes), cls} */
function editor(o){
  const tools=TOOLS().filter(x=>!o.tools||o.tools.includes(x[0]));
  return `<div class="rte-bar" role="toolbar" aria-label="${esc(t('Mise en forme'))}">${tools.map(([c,l,tt])=>`<button type="button" data-cmd="${c}" title="${esc(tt)}" aria-label="${esc(tt)}">${l}</button>`).join('')}</div>
    <div class="rte${o.cls?' '+o.cls:''}" id="${o.id}" contenteditable="true" role="textbox" aria-multiline="true"${o.label?` aria-labelledby="${o.label}"`:''}${o.placeholder?` data-ph="${esc(o.placeholder)}"`:''}>${o.html?safeHtml(o.html):''}</div>`;
}
/* contenu de l'éditeur, '' s'il ne reste que des balises vides */
const value=el=>el.textContent.replace(/ /g,' ').trim()?el.innerHTML:'';

function runCmd(btn,toast){
  const rte=btn.closest('.rte-bar').nextElementSibling;if(!rte)return;rte.focus();
  const cmd=btn.dataset.cmd;
  if(cmd==='h3'){const cur=(document.queryCommandValue('formatBlock')||'').toLowerCase();document.execCommand('formatBlock',false,cur==='h3'?'p':'h3');return}
  if(cmd==='link'){
    const url=prompt(t('Adresse du lien (https://…, mailto:… ou tel:…)'),'https://');
    if(!url)return;
    if(!/^(https?:\/\/|mailto:|tel:)\S+$/i.test(url.trim())){toast(t('Adresse de lien invalide : utilisez https://, mailto: ou tel:'));return}
    document.execCommand('createLink',false,url.trim());return;
  }
  document.execCommand(cmd,false,null);
}
/* à appeler une fois par page : boutons de la barre, collage en texte brut */
function install(toast){
  document.addEventListener('mousedown',e=>{if(e.target.closest('[data-cmd]'))e.preventDefault()}); /* garde la sélection dans l'éditeur */
  document.addEventListener('click',e=>{const c=e.target.closest('[data-cmd]');if(c)runCmd(c,toast)});
  /* collage : texte brut uniquement (évite la mise en forme parasite de Word, des pages web…) */
  document.addEventListener('paste',e=>{
    if(!e.target.closest||!e.target.closest('.rte'))return;
    e.preventDefault();
    document.execCommand('insertText',false,(e.clipboardData||window.clipboardData).getData('text/plain'));
  });
}
window.RTE={safeHtml,editor,value,install};
})();
