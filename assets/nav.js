/* Menu des pages sur téléphone (commun à index.php, inscription.php et contact.php) :
   le bouton jaune #burger, à côté des langues, ouvre et ferme la ligne d'onglets (.tabrow.open, voir app.css).
   Il se referme au choix d'un onglet, par un clic ailleurs ou avec Échap. */
(function(){
const row=()=>document.querySelector('.tabrow');
function setOpen(open){
  const r=row(),b=document.getElementById('burger');if(!r||!b)return;
  r.classList.toggle('open',open);b.setAttribute('aria-expanded',String(open));
}
document.addEventListener('click',e=>{
  if(e.target.closest('#burger')){const r=row();setOpen(!(r&&r.classList.contains('open')));return}
  if(e.target.closest('.tabs .tab')||!e.target.closest('.tabrow'))setOpen(false);
});
document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
})();
