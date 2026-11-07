<?php
/*
 * Page d'aide, version française (texte de référence). Affichée par aide.php.
 * Les versions bretonne (br.php) et anglaise (en.php) suivent le même plan, avec les mêmes ancres (id) :
 * toute modification ici est à reporter dans les deux autres (le breton est à faire relire par un·e brittophone).
 * HTML simple : h2/h3, p, ul/ol, b, a. Les liens vers l'appli restent relatifs (index.php, inscription.php…).
 */
?>
<h1>Aide</h1>
<p class="lead">Youl Vat est le planning des bénévoles du festival : chacun peut voir les créneaux et s’inscrire sans compte ; les organisateurs préparent les postes, affectent et pointent les bénévoles.</p>

<nav class="toc" aria-label="Sommaire">
  <a href="#benevoles">Bénévoles</a> · <a href="#confidentialite">Données personnelles</a> · <a href="#organisateurs">Organisateurs</a> · <a href="#jourj">Le jour J</a> · <a href="#historique">Historique des retraits</a> · <a href="#liens">Liens directs</a> · <a href="#technique">Équipe technique</a>
</nav>

<h2 id="benevoles">Pour les bénévoles</h2>
<h3>Consulter le planning</h3>
<ul>
  <li>Choisissez l’événement dans le menu en haut de page. L’onglet <b>Planning</b> s’ouvre sur le jour le plus chargé ; changez de jour avec les boutons au-dessus de la grille.</li>
  <li>Deux vues : <b>Grille</b> (frise horaire) et <b>Liste</b> (créneaux regroupés par poste, vue par défaut sur téléphone). Le choix est mémorisé.</li>
  <li>Couleur d’un créneau : <b>vert</b> = complet, <b>orange</b> = il manque du monde, <b>rouge</b> = personne.</li>
  <li>Un créneau qui commence avant 6 h appartient à la nuit du jour indiqué : « 31 décembre, 00:00–05:00 » est la nuit du 31 au 1<sup>er</sup>.</li>
  <li>Sur téléphone, le bouton jaune <b>☰</b> à côté des langues ouvre le menu des pages.</li>
</ul>
<h3>S’inscrire</h3>
<ol>
  <li>Bouton <b>S’inscrire</b> (ou « S’inscrire comme bénévole » dans l’encadré du planning).</li>
  <li>Choisissez un créneau libre, puis donnez votre prénom, votre nom, votre téléphone et votre e-mail (tous obligatoires). Vous pouvez ajouter une remarque pour l’organisation.</li>
  <li>Pour plusieurs créneaux, recommencez avec la même identité : votre fiche est réutilisée.</li>
</ol>
<p>L’inscription est refusée si le créneau vient d’être complété, si vous y êtes déjà, ou si vous êtes déjà pris·e à la même heure.</p>
<h3>Retrouver mes créneaux</h3>
<p>En haut du Planning, « Retrouver une inscription » : tapez votre prénom et votre nom (« marie dupont »), ou votre e-mail ou téléphone <b>exact</b>. « Retrouver un créneau » cherche par poste, responsable, jour ou heure (« bar 31 22h »).</p>
<h3>Changer ou me désister</h3>
<p>Pas de désinscription en ligne : bouton rouge <b>Changer / me désister</b>, qui mène à la page <b>Contact</b> de l’événement.</p>
<h3>Devenir responsable</h3>
<p>Onglet <b>Responsables</b> : tant qu’il reste de la place sur un poste de responsable, le bouton <b>Je me propose</b> mène à un formulaire d’inscription. Être responsable n’inscrit pas automatiquement sur les créneaux du poste.</p>

<h2 id="confidentialite">Données personnelles</h2>
<ul>
  <li>Le public ne voit de vous que votre prénom, l’initiale de votre nom et quelques caractères de vos coordonnées, de quoi distinguer deux homonymes : « Marie D. ******* · ma****@**** · 06******78 ».</li>
  <li>Votre téléphone, votre e-mail en entier et vos remarques ne sont visibles que par les organisateurs.</li>
  <li>Les coordonnées de la page Contact sont celles de l’organisation : elles sont publiques.</li>
</ul>

<h2 id="organisateurs">Pour les organisateurs</h2>
<h3>Connexion</h3>
<p>Bouton <b>Organisateurs</b> (sous les langues), avec le mot de passe partagé de l’équipe. Les boutons d’édition apparaissent alors. Les données se rechargent toutes les 15 secondes et après chaque modification.</p>
<h3>Événement</h3>
<ul>
  <li><b>+ Événement</b> / <b>Modifier</b> à côté du menu des événements : nom, lieu, dates, image (adresse https), coordonnées de contact, déroulé de la soirée (une étape par ligne, « 20:00 Ouverture des portes »).</li>
  <li>L’encadré « Envie de donner un coup de main ? » (Planning, Tableau de bord) et le texte de la page Contact se modifient sur place (bouton <b>Modifier</b>), une version par langue : changez de langue en haut de page pour écrire l’autre version. Sans traduction, c’est le français qui s’affiche.</li>
</ul>
<h3>Postes et créneaux</h3>
<ul>
  <li>Onglet <b>Postes &amp; créneaux</b> : <b>+ Poste</b> (nom, couleur, consignes), puis <b>+ Créneau</b> sur la carte du poste (jour, début, fin, nombre de personnes voulues).</li>
  <li>Fin avant le début = le créneau passe minuit. Début avant 6 h = nuit du jour indiqué (voir plus haut).</li>
  <li>Supprimer un poste supprime ses créneaux et leurs affectations (notées dans l’historique).</li>
</ul>
<h3>Affecter un bénévole</h3>
<ul>
  <li>Cliquez sur un créneau du planning. Dans « Affecter un bénévole », tapez quelques lettres du nom, de l’e-mail ou du téléphone, puis <b>Entrée</b> : la personne est affectée et le curseur revient dans le champ pour enchaîner.</li>
  <li>Les candidats sont triés par heures déjà planifiées (les moins chargés d’abord) ; ceux déjà occupés à cette heure sont grisés. Un chevauchement est signalé en rouge.</li>
  <li><b>Retirer</b> enlève la personne du créneau : sa fiche reste dans l’annuaire et le retrait est noté dans l’historique.</li>
</ul>
<h3>Annuaire des bénévoles</h3>
<ul>
  <li>Onglet <b>Bénévoles</b> : tous les bénévoles, y compris ceux qui n’ont (plus) aucun créneau (« disponible »). Cliquez sur un en-tête pour trier ; par défaut, les inscriptions récentes d’abord.</li>
  <li><b>+ Bénévole</b> pour une fiche saisie à la main (téléphone et e-mail facultatifs). Cliquez sur une ligne pour voir son planning ; « Copier son planning » copie ses créneaux, prêts à envoyer par SMS ou e-mail.</li>
  <li><b>Exporter CSV</b> (bénévoles × créneaux de l’événement) et <b>Sauvegarde JSON</b> (toute la base, historique compris).</li>
</ul>
<h3>Responsables</h3>
<p>Onglet <b>Responsables</b> → <b>+ Responsable</b> : titre, nombre de personnes, description, lien vers la fiche de poste, créneaux encadrés. Une personne par ligne au format « nom, téléphone, e-mail » (téléphone et e-mail facultatifs, dans n’importe quel ordre), ou « + Ajouter un bénévole » déjà inscrit. Les responsables apparaissent sous le nom des postes dans le planning.</p>

<h2 id="jourj">Le jour J</h2>
<ul>
  <li>Dans le tiroir d’un créneau, chaque bénévole a un statut <b>prévu</b>, <b>présent</b> ou <b>absent</b>. Les absents ne comptent plus dans le remplissage ni dans les heures.</li>
  <li>Le <b>Tableau de bord</b> donne le remplissage, les places à pourvoir en priorité, les heures planifiées et le déroulé de la soirée.</li>
  <li>Plusieurs organisateurs peuvent travailler en même temps : la base gère les écritures simultanées, et une inscription ne dépasse jamais le nombre de places.</li>
</ul>

<h2 id="historique">Historique des retraits</h2>
<p>Rien n’est perdu quand on retire quelqu’un. Chaque retrait est recopié (nom, téléphone, e-mail, créneau, statut, date d’inscription et date du retrait) :</p>
<ul>
  <li>bénévole retiré d’un créneau, ou retiré parce que le créneau, le poste ou l’événement a été supprimé ;</li>
  <li>personne retirée d’un poste de responsable ;</li>
  <li>fiche bénévole supprimée (copie complète, remarques comprises).</li>
</ul>
<p>Où le voir : en bas de l’onglet <b>Bénévoles</b>, « Historique des retraits » (pour l’événement choisi, plus les fiches supprimées), et dans la fiche de chaque bénévole. Visible des organisateurs seulement ; inclus dans la sauvegarde JSON.</p>

<h2 id="liens">Liens directs</h2>
<p>Chaque nom de poste, de créneau ou de bénévole suivi du symbole 🔗 est un lien à garder en favori ou à envoyer. Les tiroirs ont un bouton « Copier le lien ». Le lien d’une fiche bénévole n’affiche les coordonnées complètes qu’aux organisateurs connectés.</p>

<h2 id="technique">Pour l’équipe technique</h2>
<ul>
  <li>PHP 8 + SQLite, sans installation : déposer le dossier par FTP. Le mot de passe se règle dans <code>config.php</code>.</li>
  <li>Sauvegarde : <b>Sauvegarde JSON</b> (onglet Bénévoles), pas une copie FTP de la base pendant l’utilisation.</li>
  <li>Avant chaque mise en ligne : <code>php test/run.php</code> (tests automatiques), puis la procédure de <code>test/PROCEDURE.md</code>. Le dossier <code>test/</code> n’est pas à déployer.</li>
</ul>
