# Procédure de test manuel

Tests à refaire avant une mise en ligne qui touche à l'interface. Ils se font sur une **copie jetable** : la vraie base `data/regie.sqlite` n'est jamais modifiée.

## 1. Lancer le bac à sable

```sh
sh test/sandbox.sh                      # copie dans $TMPDIR/regie-test, port 8765
sh test/sandbox.sh /tmp/regie-test 8765 # ou dossier et port au choix
```

Le script copie l'appli hors du dépôt, fait une copie cohérente de la base (`sqlite3 .backup`), puis lance `php -S`. Ouvrir `http://localhost:8765/index.php`. `Ctrl+C` arrête le serveur. Relancer le script repart d'une copie neuve (les écritures de test sont perdues, c'est voulu).

⚠ Le bac à sable est une **copie** : après avoir modifié un fichier du dépôt, relancer le script (ou recopier le fichier) avant de retester.

Connexion organisateurs : bouton « Organisateurs », mot de passe de `config.php` (demander à l'équipe ; ne pas l'écrire ici).

Pour modifier la base « de l'extérieur » (tester le rafraîchissement automatique) :

```sh
sqlite3 $TMPDIR/regie-test/data/regie.sqlite "update benevoles set competences='test' where id='…';"
```

## 2. Liste à choix avec recherche (`assets/combo.js`)

### Tiroir d'un créneau (connecté) — « Affecter un bénévole »

Ouvrir un créneau qui a de la place : `index.php?event=<id>&creneau=<id>`.

| # | Action | Attendu |
|---|--------|---------|
| 1 | Cliquer dans le champ | La liste complète s'ouvre, triée par heures (les moins chargés d'abord), 1er choix possible en jaune |
| 2 | Taper `celine cour` (sans accent) | « Céline Courvoisier » seule |
| 3 | Taper le début d'un e-mail | Le bénévole correspondant |
| 4 | Taper `06 71 22` puis `067122` | Mêmes résultats (chiffres comparés sans espaces) |
| 5 | Taper `ma` | D'abord les noms qui contiennent « ma », ensuite ceux trouvés seulement par e-mail |
| 6 | Taper `zzzz` | « Aucun résultat » |
| 7 | Chercher un bénévole « déjà occupé » | Grisé, ni clic ni Entrée ne le choisissent ; ↑/↓ le sautent |
| 8 | Échap | La liste se ferme, **le tiroir reste ouvert** |
| 9 | Taper un nom, Entrée | Bénévole affecté (toast), compteur `n/besoin` mis à jour, le curseur revient dans le champ |
| 10 | Enchaîner un 2e nom + Entrée | 2e affectation sans toucher la souris |
| 11 | Taper `mar` et attendre 20 s après une modif de la base (voir §1) | Le texte reste, la liste reste ouverte (pas de rafraîchissement pendant la saisie) |
| 12 | Quitter le champ, attendre 20 s | Le rafraîchissement reprend (le tiroir reste ouvert) |

### Formulaire d'un poste de responsable — « + Ajouter un bénévole »

Onglet Responsables → « Modifier » sur une carte.

| # | Action | Attendu |
|---|--------|---------|
| 13 | Taper un nom, Entrée | Le nom s'affiche dans le champ, le formulaire **n'est pas** envoyé |
| 14 | « Enregistrer » | Toast « Modifications enregistrées », la personne apparaît sur la carte avec ses contacts |
| 15 | Vérifier en base | `select * from responsable_personnes where responsable_id='…';` contient le `benevole_id` |

### Public (déconnecté)

| # | Action | Attendu |
|---|--------|---------|
| 16 | Ouvrir un créneau | Pas de champ de recherche, bouton « S'inscrire sur ce créneau » |

## 3. Pied de page (`library/footer.php`)

| # | Page | Attendu |
|---|------|---------|
| 17 | `index.php`, `inscription.php`, `contact.php` | Pied de page gris centré « Encore une réalisation Breizhvibe Lokorn » |
| 18 | `?lang=br` / `?lang=en` | « Un oberenn all gant Breizhvibe Lokorn » / « Another Breizhvibe Lokorn creation » |
| 19 | Clic sur le lien | `https://wave.bzh/en/breizhvibe/#youlvat-case` dans un nouvel onglet |

## 3 bis. En-tête et balises SEO (`library/header.php`, `library/page.php`)

| # | Action | Attendu |
|---|--------|---------|
| 23 | `curl -s 'http://localhost:8765/index.php?event=<id>' \| grep -E '<title>\|description\|og:\|evMeta'` | Titre « <événement> · Planning des bénévoles », description, `og:image`, lieu et dates dans `#evMeta` (sans JavaScript) |
| 24 | Idem `inscription.php?event=<id>`, `contact.php?event=<id>`, `index.php?creneau=<id>` | Bon événement (déduit du créneau), liens des onglets avec `?event=` |
| 25 | `index.php` sans événement, ou `?event=inconnu` | « Choisissez un événement », onglets et ☰ cachés |
| 26 | Dans le navigateur, `?lang=br` | En-tête traduit (dates en breton), sélecteur rempli, changement d'onglet OK, pas d'erreur console |

## 4. Téléphone (≤ 560 px)

Outils de développement du navigateur → mode appareil, 400 px de large (ou une `<iframe>` de 400 px).

| # | Action | Attendu |
|---|--------|---------|
| 20 | Tiroir créneau, champ de recherche | Champ et bouton « Affecter » sur une ligne, liste aussi large que le champ, pas de défilement horizontal |
| 21 | Bas de page | Pied de page lisible, pas masqué par la barre du téléphone |

## 5. Console

| # | Action | Attendu |
|---|--------|---------|
| 22 | Recharger chaque page, connecté et déconnecté | Aucune erreur JavaScript dans la console |

## Journal

| Date | Qui | Résultat |
|------|-----|----------|
| 07/11/2026 | Claude (curl + Chrome sur le serveur de dev, lecture seule) | 23–26 OK (en-tête commun). |
| 07/11/2026 | Claude (Chrome, bac à sable, copie de la base de dev) | 1–20 et 22 OK. 21 non vérifié sur un vrai téléphone. Tri « noms d'abord » (5) ajouté à la suite du test. |
