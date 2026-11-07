<?php
/*
 * En-tête commun (index.php, inscription.php, contact.php), écrit en HTML par le serveur.
 * Inclus juste après <body> : <?php include __DIR__ . '/library/header.php'; ?>
 * Variables attendues (préparées par la page avec library/page.php) :
 *   $page   'index' | 'inscription' | 'contact'
 *   $ev     événement courant (page_event()) ou null
 *   $events liste des événements (page_events())
 * Le JavaScript de chaque page remet ensuite à jour le sélecteur, les liens et les textes (traduction).
 * ⚠ data-i18n remplace tout le contenu de l'élément : ne le mettre que sur un texte fixe.
 * Onglets : boutons sur index.php (changement d'onglet sans recharger), liens sur les deux autres pages.
 */
$evId  = $ev ? rawurlencode($ev['id']) : '';
$q     = $evId !== '' ? '?event=' . $evId : '';
$img   = ev_image($ev);
$when  = ev_when($ev);
$past  = $ev && (($ev['fin'] ?: $ev['debut']) < gmdate('Y-m-d'));
$isIdx = $page === 'index';
$tabs  = [ // clé, classe de couleur, libellé
    ['plan', 't-white', 'Planning'], ['dash', '', 'Tableau de bord'], ['bens', 't-yellow', 'Bénévoles'],
    ['resps', 't-yellow', 'Responsables'], ['postes', 't-white', 'Postes & créneaux'],
];
?>
<header class="band">
  <div class="wrap band-in">
    <div class="brand">
      <a class="mark" href="./" title="Tous les événements" data-i18n-title="Tous les événements">YV</a>
      <div class="brand-t">YOUL VAT<small data-i18n="Régie Bénévoles">Régie Bénévoles</small></div>
    </div>
    <div class="evcenter">
      <img id="evImg" class="evimg" alt="<?= h($ev['nom'] ?? '') ?>"<?= $img ? ' src="' . h($img) . '"' : ' hidden' ?>>
      <div class="brand-b">
        <div class="evline">
          <label for="evSel" class="sr" data-i18n="Événement">Événement</label>
          <select id="evSel">
            <option value=""><?= $events ? 'Tous les événements' : 'Aucun événement' ?></option>
<?php foreach ($events as $e): ?>
            <option value="<?= h($e['id']) ?>"<?= $ev && $e['id'] === $ev['id'] ? ' selected' : '' ?>><?= h($e['nom']) ?></option>
<?php endforeach; ?>
          </select>
<?php if ($isIdx): ?>
          <button class="btn band-btn sm adm" id="evEdit" type="button" hidden data-i18n="Modifier">Modifier</button>
          <button class="btn band-btn sm adm quiet" id="evNew" type="button" data-i18n="+ Événement">+ Événement</button>
<?php endif; ?>
        </div>
<?php if ($ev): ?>
        <div class="brand-s" id="evMeta"><?= h($when) ?></div>
<?php else: ?>
        <div class="brand-s" id="evMeta" data-i18n="Choisissez un événement">Choisissez un événement</div>
<?php endif; ?>
      </div>
    </div>
    <div class="evpick">
<?php if ($isIdx): ?>
      <div class="evpick-col">
<?php endif; ?>
      <div class="langrow"><div class="langsw" role="group" aria-label="Yezh / Langue"></div><button class="burger" id="burger" type="button" aria-expanded="false" title="Menu" data-i18n-title="Menu"<?= $isIdx && !$ev ? ' hidden' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
<?php if ($isIdx): ?>
        <button class="btn band-btn sm" id="login" type="button" hidden data-i18n="Organisateurs">Organisateurs</button>
        <button class="btn band-btn sm" id="logout" type="button" hidden data-i18n="Déconnexion">Déconnexion</button>
      </div>
<?php endif; ?>
    </div>
  </div>
  <div class="wrap tabrow"<?= $isIdx && !$ev ? ' hidden' : '' ?>>
  <nav class="tabs"<?= $isIdx ? ' role="tablist"' : '' ?> aria-label="Sections">
<?php foreach ($tabs as [$k, $cls, $lab]):
    $c = trim('tab ' . $cls); ?>
<?php if ($isIdx): ?>
    <button class="<?= $c ?>" role="tab" data-tab="<?= $k ?>" type="button" data-i18n="<?= h($lab) ?>"><?= h($lab) ?></button>
<?php else: ?>
    <a class="<?= $c ?>" data-nav="<?= $k ?>" href="index.php<?= $q ?><?= $q && $k !== 'plan' ? '&amp;tab=' . $k : '' ?>" data-i18n="<?= h($lab) ?>"><?= h($lab) ?></a>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($isIdx): ?>
    <a class="tab" id="contactTab" href="contact.php<?= $q ?>" data-i18n="Contact">Contact</a>
<?php else: ?>
    <a class="tab" data-nav="contact" href="contact.php<?= $q ?>"<?= $page === 'contact' ? ' aria-current="page" aria-selected="true"' : '' ?> data-i18n="Contact">Contact</a>
<?php endif; ?>
  </nav>
  <div class="tabacts">
    <a class="btn pri sm" <?= $isIdx ? 'id="signupLink"' : 'data-nav="signup"' ?> href="inscription.php<?= $q ?>"<?= $page === 'inscription' ? ' aria-current="page"' : '' ?><?= $past && !$isIdx ? ' hidden' : '' ?> data-i18n="S’inscrire">S’inscrire</a>
    <a class="btn danger fill sm" <?= $isIdx ? 'id="changeLink"' : 'data-nav="change"' ?> href="contact.php<?= $q ?>" data-i18n="Changer / me désister">Changer / me désister</a>
  </div>
  </div>
</header>
