# Tests

## 0. Tests automatiques (à lancer avant chaque dépôt FTP)

```sh
php test/run.php              # tout (≈ 3 s)
php test/run.php signup       # seulement les groupes dont le nom contient « signup »
php test/run.php --keep       # garde le bac à sable pour inspecter la base ensuite
```

`run.php` crée sa propre copie de l'appli dans le dossier temporaire, avec une **config de test** (mot de passe propre au test, base neuve chargée avec `seed_demo.sql`) et son propre serveur `php -S` sur un port libre. La vraie `config.php` et la vraie base ne sont jamais lues. Code de sortie 0 = tout passe, 1 = échec (liste des échecs à la fin). Il couvre :

- **unitaires** : masquage nom / e-mail / tél, règle de nuit et passage de minuit, nettoyage du texte riche, dates ;
- **pages** : en-tête et pied de page communs, page d'aide (trois langues, onglet sur toutes les pages), `<title>`, description, Open Graph, événement déduit de `?creneau=` / `?poste=`, échappement HTML, suffixes `?v=` ;
- **API** : lecture publique (données privées masquées, notes retirées), CSRF, écritures refusées hors connexion, connexion / déconnexion, mode WAL ;
- **inscription** : champs obligatoires, champ piège, réutilisation de la fiche sans écraser les contacts, remarques nettoyées et sans doublon, chevauchements, règle de nuit, créneau complet, absents, **6 inscriptions simultanées sur 2 places**, limite de 20 par heure ;
- **recherche** e-mail / téléphone exacte seulement, limite de 30 par heure ;
- **responsables** : création, inscription publique, poste pourvu, modification en différentiel, masquage ;
- **historique des retraits** : retrait d'un créneau, suppression d'un créneau, personne retirée d'un poste de responsable, fiche supprimée (copie complète) ; la fiche d'un bénévole sans créneau reste ; jamais envoyé au public ; inclus dans la sauvegarde JSON ;
- **organisateurs** : validations, clés étrangères, cascades, exports CSV / JSON ; fichiers `.htaccess` présents ; aucune erreur PHP dans le journal du serveur.

Les tests ont été vérifiés en cassant volontairement le code (limite de places retirée, e-mail non masqué, nom non échappé, historique non écrit) : chaque fois, le bon test échoue.

**JavaScript** (`assets/combo.js`) : ouvrir `test/combo.html` via un serveur local (`php -S localhost:8001` à la racine, puis `http://localhost:8001/test/combo.html`). Le titre de l'onglet commence par ✓ ou ✗ ; détail dans la page et dans `window.__results`.

Le dossier `test/` n'est **pas à déployer** (il contient un `.htaccess` qui le bloque et `run.php` refuse de tourner hors ligne de commande, au cas où).

---

# Procédure de test manuel

Ce que les tests automatiques ne voient pas (affichage, clavier, téléphone). À refaire avant une mise en ligne qui touche à l'interface. Ils se font sur une **copie jetable** : la vraie base `data/regie.sqlite` n'est jamais modifiée.

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

## 3 ter. Historique des retraits et page d'aide

| # | Action | Attendu |
|---|--------|---------|
| 27 | Connecté : ouvrir un créneau, « Retirer » un bénévole | Toast « Retiré du créneau » ; dans l'onglet Bénévoles, la personne est toujours là (« disponible ») |
| 28 | Bas de l'onglet Bénévoles → « Historique des retraits » | Ligne : date, nom (lien vers la fiche), « Retiré de <poste> · jour heures », date d'inscription |
| 29 | Ouvrir la fiche de ce bénévole | Rubrique « Historique » avec le même retrait |
| 30 | Supprimer une fiche bénévole (formulaire → Supprimer) | Dans l'historique : « Fiche supprimée » avec ses coordonnées, et ses créneaux marqués « fiche supprimée » |
| 31 | Déconnecté | Aucun historique visible (ni onglet, ni fiche, ni `historique` dans `api.php?action=all`) |
| 32 | `aide.php?event=<id>` puis BZH / EN | Onglet « Aide » / « Sikour » / « Help » en évidence, texte dans la bonne langue, sommaire cliquable, dates de l'en-tête traduites |

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
| 07/11/2026 | Claude | `run.php` : 209 vérifications OK (historique, page d'aide). 27–29 et 32 (BZH) OK dans Chrome (bac à sable) ; 30–31 couverts par `run.php`. |
| 07/11/2026 | Claude | `run.php` : 171 vérifications OK ; `combo.html` : 40 OK (Chrome). |
| 07/11/2026 | Claude (curl + Chrome sur le serveur de dev, lecture seule) | 23–26 OK (en-tête commun). |
| 07/11/2026 | Claude (Chrome, bac à sable, copie de la base de dev) | 1–20 et 22 OK. 21 non vérifié sur un vrai téléphone. Tri « noms d'abord » (5) ajouté à la suite du test. |
