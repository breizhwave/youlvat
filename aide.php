<?php
// Page publique d'aide (AIDE / SIKOUR / HELP) : mode d'emploi pour les bénévoles et les organisateurs.
// Le texte est dans library/aide/fr.php (référence), br.php et en.php : les trois versions sont écrites dans le HTML
// (lisibles par les moteurs de recherche) ; seule celle de la langue choisie est affichée.
require_once __DIR__ . '/library/auth.php';
require_once __DIR__ . '/library/page.php';
start_session();
$setupError = null;
try { db(); } catch (Throwable $e) { $setupError = $e instanceof DbSetupError ? $e->getMessage() : 'Connexion à la base impossible.'; }
$page   = 'aide';
$ev     = $setupError ? null : page_event();
$events = $setupError ? [] : page_events();
$when   = ev_when($ev);
// langue affichée sans JavaScript : ?lang=, sinon français (le JavaScript prend ensuite la langue mémorisée)
$lang   = in_array($_GET['lang'] ?? '', ['br', 'fr', 'en'], true) ? $_GET['lang'] : 'fr';
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php page_meta('Aide', $ev, 'Mode d’emploi du planning des bénévoles : inscription, consultation, organisation, historique.'); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
<script src="assets/i18n.js?v=<?= filemtime(__DIR__ . '/assets/i18n.js') ?>"></script>
<script src="assets/nav.js?v=<?= filemtime(__DIR__ . '/assets/nav.js') ?>"></script>
</head>
<body<?= is_admin() ? '' : ' class="ro"' ?>>
<?php include __DIR__ . '/library/header.php'; ?>
<main class="wrap" id="main">
<?php foreach (['fr', 'br', 'en'] as $l): ?>
  <article class="panel help rich" lang="<?= $l ?>" data-help="<?= $l ?>"<?= $l === $lang ? '' : ' hidden' ?>>
<?php include __DIR__ . "/library/aide/$l.php"; ?>
  </article>
<?php endforeach; ?>
</main>
<?php include __DIR__ . '/library/footer.php'; ?>

<script>
(function(){
const {t}=I18N;
I18N.applyStatic();
/* version de la langue choisie (mémorisée dans rb.lang), les autres restent dans la page pour les moteurs de recherche */
document.querySelectorAll('[data-help]').forEach(a=>{a.hidden=a.dataset.help!==I18N.lang});
const EV=<?= json_encode($ev ? ['lieu' => $ev['lieu'], 'debut' => $ev['debut'], 'fin' => $ev['fin']] : null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
/* lieu et dates de l'en-tête dans la langue choisie (le serveur les écrit en français) */
if(EV){const f=I18N.fmtDay;document.getElementById('evMeta').textContent=[EV.lieu,EV.debut?f(EV.debut,{day:'numeric',month:'long'})+(EV.fin&&EV.fin!==EV.debut?' → '+f(EV.fin,{day:'numeric',month:'long',year:'numeric'}):''):''].filter(Boolean).join(' · ')}
const ev=document.getElementById('evSel');
if(ev&&ev.options[0])ev.options[0].text=t(ev.options[0].text); /* « Tous les événements » / « Aucun événement » */
document.title=(ev&&ev.value?ev.options[ev.selectedIndex].text+' · ':'')+t('Aide');
document.addEventListener('change',e=>{if(e.target.id==='evSel')location.href=location.pathname+(e.target.value?'?event='+encodeURIComponent(e.target.value):'')+location.hash});
})();
</script>
</body>
</html>
