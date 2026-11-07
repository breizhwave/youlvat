/* Liste déroulante avec recherche (sans bibliothèque).
 * Transforme un <select data-combo> en champ texte + liste filtrée au clavier.
 * Le <select> reste dans la page (caché) et garde la valeur : le code qui lit select.value ne change pas.
 *   data-combo            active la recherche
 *   data-combo-go="#sel"  au choix (Entrée ou clic), clique ce bouton (ex. « Affecter »)
 *   <option data-s="…">   texte de recherche en plus (e-mail, téléphone…)
 * Recherche : tous les mots doivent apparaître (sans accents ni casse) ; les chiffres sont aussi comparés sans espaces.
 * Combo.enhance(racine) après chaque rendu ; Combo.active() = un champ de recherche a le focus (ne pas re-rendre). */
(function(){
const fold=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const tr=s=>(window.I18N&&I18N.t)?I18N.t(s):s;
let uid=0;

function enhance(root){
  (root||document).querySelectorAll('select[data-combo]:not([data-combo-on])').forEach(build);
}
function build(sel){
  sel.setAttribute('data-combo-on','1');
  const box=document.createElement('div');box.className='combo';
  const id=sel.id?sel.id+'-q':'combo-q'+(++uid);const lid=id+'-l';
  const inp=document.createElement('input');
  inp.type='search';inp.id=id;inp.autocomplete='off';inp.spellcheck=false;
  inp.setAttribute('role','combobox');inp.setAttribute('aria-expanded','false');inp.setAttribute('aria-controls',lid);inp.setAttribute('aria-autocomplete','list');
  const ph=sel.options[0]&&!sel.options[0].value?sel.options[0].text:'';
  inp.placeholder=ph?ph.replace(/…$/,'')+' — '+tr('tapez un nom…'):tr('tapez un nom…');
  if(sel.getAttribute('aria-label'))inp.setAttribute('aria-label',sel.getAttribute('aria-label'));
  const ul=document.createElement('ul');ul.className='combo-l';ul.id=lid;ul.setAttribute('role','listbox');ul.hidden=true;
  sel.parentNode.insertBefore(box,sel);box.append(inp,ul,sel);sel.hidden=true;sel.tabIndex=-1;
  if(sel.id){const lab=document.querySelector(`label[for="${CSS.escape(sel.id)}"]`);if(lab)lab.htmlFor=id}

  const items=[...sel.options].filter(o=>o.value).map(o=>({o,name:fold(o.text),hay:fold(o.text+' '+(o.dataset.s||'')),dig:(o.text+' '+(o.dataset.s||'')).replace(/\D/g,'')}));
  let shown=[],cur=-1;
  const cur0=items.find(x=>x.o.value===sel.value);if(cur0)inp.value=label(cur0.o);

  function label(o){return o.text}
  function match(q){
    const ws=fold(q).split(/\s+/).filter(Boolean);if(!ws.length)return items;
    const ok=items.filter(x=>ws.every(w=>x.hay.includes(w)||(/^\d{2,}$/.test(w)&&x.dig.includes(w))));
    /* d'abord ceux dont le nom correspond, puis ceux trouvés par e-mail / téléphone (ordre d'origine conservé dans chaque groupe) */
    const byName=ok.filter(x=>ws.every(w=>x.name.includes(w)));
    return byName.concat(ok.filter(x=>!byName.includes(x)));
  }
  function draw(){
    const q=inp.value;shown=match(q);
    if(cur>=shown.length)cur=shown.length-1;
    if(cur<0)cur=shown.findIndex(x=>!x.o.disabled);
    ul.innerHTML='';
    if(!shown.length){const li=document.createElement('li');li.className='combo-none';li.textContent=tr('Aucun résultat');ul.append(li)}
    shown.forEach((x,i)=>{const li=document.createElement('li');li.setAttribute('role','option');li.id=lid+'-'+i;
      li.textContent=x.o.text;li.dataset.i=i;
      if(x.o.disabled){li.className='dis';li.setAttribute('aria-disabled','true')}
      if(i===cur){li.classList.add('cur');li.setAttribute('aria-selected','true');inp.setAttribute('aria-activedescendant',li.id)}
      ul.append(li)});
    open(true);
    const c=ul.querySelector('.cur');if(c)c.scrollIntoView({block:'nearest'});
  }
  function open(v){ul.hidden=!v;inp.setAttribute('aria-expanded',String(v));if(!v)inp.removeAttribute('aria-activedescendant')}
  function move(d){
    if(!shown.length)return;let i=cur;
    for(let k=0;k<shown.length;k++){i=(i+d+shown.length)%shown.length;if(!shown[i].o.disabled)break}
    cur=i;draw();
  }
  function pick(i){
    const x=shown[i];if(!x||x.o.disabled)return;
    sel.value=x.o.value;inp.value=label(x.o);open(false);
    sel.dispatchEvent(new Event('change',{bubbles:true}));
    const go=sel.dataset.comboGo&&document.querySelector(sel.dataset.comboGo);if(go)go.click();
  }
  inp.addEventListener('input',()=>{sel.value='';cur=-1;draw()});
  inp.addEventListener('focus',()=>{if(!sel.value){cur=-1;draw()}});
  inp.addEventListener('click',()=>{if(ul.hidden){cur=-1;draw()}});
  inp.addEventListener('keydown',e=>{
    if(e.key==='ArrowDown'){e.preventDefault();ul.hidden?draw():move(1)}
    else if(e.key==='ArrowUp'){e.preventDefault();ul.hidden?draw():move(-1)}
    else if(e.key==='Enter'){if(!ul.hidden&&cur>=0){e.preventDefault();pick(cur)}else if(sel.value&&!sel.dataset.comboGo){/* laisse le formulaire se soumettre */}else e.preventDefault()}
    else if(e.key==='Escape'){if(!ul.hidden){e.preventDefault();e.stopPropagation();open(false)}}
    else if(e.key==='Tab')open(false);
  });
  ul.addEventListener('mousedown',e=>{e.preventDefault();const li=e.target.closest('li[data-i]');if(li)pick(+li.dataset.i)});
  inp.addEventListener('blur',()=>{setTimeout(()=>open(false),0);
    if(!sel.value&&inp.value){const ok=shown.filter(x=>!x.o.disabled);if(ok.length===1){sel.value=ok[0].o.value;inp.value=label(ok[0].o)}}});
}
function active(){const a=document.activeElement;return !!(a&&a.closest&&a.closest('.combo'))}
window.Combo={enhance,active};
})();
