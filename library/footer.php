<?php
/*
 * Pied de page commun (index.php, inscription.php, contact.php).
 * À modifier ici : il est inclus juste après </main> de chaque page (avant le script, pour que la traduction s'applique) avec
 *   <?php include __DIR__ . '/library/footer.php'; ?>
 * Textes : data-i18n="texte français" (traduction dans assets/i18n.js, dictionnaires BR et EN).
 *   ⚠ data-i18n remplace TOUT le contenu de l'élément au chargement : le mettre sur un <span> qui ne contient
 *   que le texte (pas d'image ni de balise dedans), et garder la clé identique au texte français.
 * Liens externes : target="_blank" rel="noopener".
 */
?>
<footer class="site-foot">
  <div class="wrap">
    <a href="https://wave.bzh/en/breizhvibe/#youlvat-case" target="_blank" rel="noopener"><img src="https://wave.bzh/assets/img/wave-2024-150.png" alt="wave.bzh"><br><span data-i18n="Encore une réalisation : Breizhvibe Lokorn">Encore une réalisation : Breizhvibe Lokorn</span></a>
  </div>
</footer>
