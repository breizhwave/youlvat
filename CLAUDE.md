# Régie Bénévoles — contexte projet

Outil de planning des bénévoles pour le **Fest-noz Vras Kalanna** (Quimper, 29/12/2026 → 02/01/2027, soirée du 31/12).
Il est d'abord né sous forme de prototype claude.ai (voir `reference/ui-prototype.html`). **On le porte maintenant en PHP + SQLite** pour pouvoir l'héberger n'importe où.

## Décisions déjà prises (ne pas remettre en cause sans demander)

- **Stockage : SQLite via PDO (`pdo_sqlite`)**. Priorité absolue : la portabilité entre hébergeurs PHP mutualisés. Migrer = copier le dossier.
  - Rejeté : fichiers JSON (écritures concurrentes risquées le soir J), MySQL (trop de friction à chaque migration), Supabase (dépendance externe, pause des projets gratuits après 7 jours d'inactivité).
  - Écrire du SQL standard pour garder une porte de sortie vers MySQL (DSN dans `config.php`).
- **Données privées limitées** : pour le public, on ne voit d'un bénévole que le prénom, l'initiale du nom, les 2 premiers caractères de l'e-mail et les 2 premiers et les 2 derniers chiffres du téléphone, de quoi distinguer deux homonymes (« Marie D. ******* · ✉ ma****@**** · ☎ 06******78 » : `mask_nom()`, `mask_email()`, `mask_tel()` dans `library/tables.php`, appliqués par l'API hors session admin ; les valeurs complètes ne sont jamais envoyées au public). Les organisateurs voient tout. Le téléphone et l'e-mail sont **obligatoires à l'inscription publique** (vérifié aussi côté serveur), facultatifs dans la fiche saisie par les organisateurs. En entier, ils ne sont visibles que par les organisateurs. Les formulaires l'indiquent.
- **L'interface du prototype plaît** : la réutiliser telle quelle (HTML/CSS/JS vanilla, polices Google Fonts), mais en **thème clair uniquement** : le thème sombre (qui suivait le réglage du téléphone) a été retiré. Seule la couche de données change.
- Le Google Sheet d'origine n'est plus affiché ni modifiable dans l'interface. La colonne `sheet_url` est conservée en base (données historiques).
- **Choix de l'événement par l'URL** : `index.php?event=<id>` et `inscription.php?event=<id>`. Sans paramètre (ou avec un id inconnu), on affiche seulement la liste des événements. Le sélecteur de l'en-tête recharge la page avec le bon `?event=`.
- **Onglets et jour dans l'URL** : `index.php?event=<id>&tab=dash|bens|resps|postes` et, pour le planning, `&day=AAAA-MM-JJ`. Sans `tab`, la page s'ouvre sur le **Planning** (premier onglet). Changer d'onglet ou de jour met l'URL à jour (`history.pushState`), le bouton retour fonctionne. L'onglet n'est plus mémorisé dans `localStorage`.
- **Liens directs (favoris)** : `index.php?event=<id>&creneau=<id>` ouvre le créneau (sur son jour), `&benevole=<id>` ouvre la fiche planning du bénévole, `&tab=postes&poste=<id>` met en évidence la carte du poste. `event` peut être omis (déduit du créneau, du poste ou de la dernière affectation du bénévole) et est remis dans l'URL. Ouvrir un créneau ou une fiche met l'URL à jour (`pushState`, le bouton retour ferme) ; la fermer retire le paramètre. Partout où un nom de poste, de bénévole ou de créneau est affiché **sans autre action**, il devient un lien vers son permalien, suivi d'un petit symbole de lien (`plink()`, classe `.plink`) ; boutons « Copier le lien » dans les tiroirs créneau et bénévole. Structure complète, emplacements et code : **`permalink.md`** (à tenir à jour).
- **Image par événement** : `events.image_url` (https uniquement), affichée à côté du sélecteur et sur les cartes de la liste. La colonne est ajoutée automatiquement aux bases existantes (`migrate()` dans `library/db.php`).
- **Consultation publique** : tout le monde voit le planning (`index.php`) sans compte, sur ordinateur comme sur téléphone. Sans session admin, l'API retire les `notes` des bénévoles et masque `nom`, `email` et `tel` (voir ci-dessus).
- **Un seul compte partagé** pour administrer (mot de passe dans `config.php`). Pas de comptes par responsable de stand. Tant que `admin_password_hash` est vide, personne ne peut administrer.
- **Inscription publique** (`inscription.php`) : un bénévole choisit un créneau libre et donne son prénom, son nom, son téléphone et son e-mail (tous obligatoires), sans compte. Si le nom existe déjà (sans tenir compte de la casse), la fiche est réutilisée. Le tél/e-mail saisis complètent alors seulement les champs vides : ils n'écrasent jamais un contact existant. Refus si le créneau est complet, si la personne y est déjà ou si elle est prise à la même heure. Un champ facultatif « Avez-vous des remarques ou suggestions à nous transmettre ? » (1000 caractères max, nettoyé par `clean_remarques()` dans `api.php` : balises HTML retirées, puis seuls lettres, chiffres, espaces, retours à la ligne, ponctuation courante et `&` sont gardés) est ajouté aux `notes` de la fiche, privées, sans doublon si la même remarque est renvoyée avec un autre créneau. Pas de désinscription en ligne : on contacte l'organisation. La page le dit, et un bouton rouge « Changer / me désister » dans l'en-tête (à côté de « S'inscrire » sur la ligne des onglets de `index.php`, visibles aussi pour les organisateurs connectés) mène à `contact.php?event=<id>`. Le bouton « Organisateurs » est sous le sélecteur de langue. `inscription.php` et `contact.php` reprennent la même ligne d'onglets que `index.php` (des liens vers `index.php?event=<id>&tab=…`, plus « Contact », « S'inscrire » et « Changer / me désister », via `navLinks()`), avec l'onglet de la page en cours mis en évidence.

- **Page contact** (`contact.php?event=<id>`, publique). Elle affiche l'e-mail (`events.contact`), le téléphone (`events.contact_tel`) et d'autres informations en texte libre, une par ligne (`events.contact_infos`, où les liens web et les e-mails deviennent cliquables). Ces champs se modifient dans le formulaire de l'événement. Elle est liée depuis l'onglet « Contact » du planning, l'en-tête de la page d'inscription et le tableau de bord. Ces coordonnées sont **publiques**, contrairement au tél/e-mail des bénévoles. Les organisateurs connectés ont un bouton « Modifier la page » : un éditeur intégré (`assets/rte.js`, partagé avec l'encadré d'appel du planning : `contenteditable` + `execCommand`, sans bibliothèque) pour le texte de la page, avec une version par langue (`events.contact_html` en français, `events.contact_html_br` en breton, `events.contact_html_en` en anglais ; si la version de la langue est vide, on affiche le français). Au collage, on ne garde que le texte brut. Le HTML est **nettoyé côté serveur** (`library/html.php`, liste blanche : p, br, b/strong, i/em, u, a[href https/http/mailto/tel], ul/ol/li, h3/h4, blockquote) et de nouveau à l'affichage.
- **Trilingue brezhoneg / français / English** (`assets/i18n.js`). Tout texte d'interface passe par `t('texte français')` ou `tn(n,'singulier','pluriel')`, et le texte français sert de clé des dictionnaires breton (`BR`) et anglais (`EN`). Textes fixes du HTML : `data-i18n="…"`. Langue : `?lang=br|fr|en` (mémorisée dans `rb.lang`), français par défaut. Sélecteur à droite de « Organisateurs ». Les données (noms de postes, consignes, déroulé) ne sont pas traduites. **Toute nouvelle chaîne doit être ajoutée aux dictionnaires breton et anglais.** Les traductions sont à faire relire par un·e brittophone.

## Contraintes techniques

- **Dossier `library/`** (ex-`lib/`, renommé le 30/10/2026 : problème de droits sur `lib/` chez l'hébergeur). Sur le serveur, l'ancien `lib/` n'est plus utilisé et peut être supprimé dès que les droits le permettent.

- PHP ≥ 8.0, **sans Composer ni framework**, sans étape de build. Un seul dossier à déposer par FTP.
- Chemins relatifs partout (l'app doit marcher dans un sous-dossier, ex. `https://asso.bzh/regie/`).
- Le site de production (`youlvat.kalanna.bzh`) passe par Cloudflare, qui garde les fichiers statiques 4 h : `assets/app.css` et `assets/i18n.js` sont appelés avec `?v=<?= filemtime(…) ?>` pour qu'une nouvelle version soit prise tout de suite. Garder ce suffixe sur tout nouveau fichier statique.
- La base est créée automatiquement au premier lancement à partir de `database/schema.sql`.
- Pour chaque connexion PDO : `PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;`
- Les dossiers `data/` (qui contient `regie.sqlite`) et `database/` (schéma et seed) sont protégés par un `.htaccess` (`Require all denied` + fallback `Deny from all`) et un `index.php` vide. `protect_dir()` (`library/db.php`) recrée ceux de `data/` s'ils manquent sur le serveur. Si `data/` n'est pas inscriptible, l'app doit l'expliquer clairement.
- **Écritures simultanées (SQLITE_BUSY)** : WAL (les lectures ne bloquent pas les écritures) + `busy_timeout` de 5 s. Une transaction s'ouvre avec `tx()` (`library/db.php`, `BEGIN IMMEDIATE`), jamais avec `beginTransaction()`. Fermer les curseurs (`closeCursor()` après un `fetch()` d'une seule ligne) avant `tx()` : avec une lecture encore ouverte, SQLite renvoie BUSY tout de suite au lieu d'attendre. Si l'attente dépasse 5 s, l'API répond 503 `busy` (rien n'est écrit) et `api()` réessaie une fois côté navigateur. Le mode réellement obtenu est renvoyé aux organisateurs dans `action=all` (`dbMode`, `wal` attendu) ; sinon un avertissement est écrit une fois dans le journal PHP. Sauvegarde : passer par `export.php`, pas par une copie FTP de `regie.sqlite` pendant l'utilisation.
- Requêtes préparées uniquement. Réponses JSON en UTF-8 (`JSON_UNESCAPED_UNICODE`).

## Arborescence visée

```
regie/
  index.php          interface (portée de reference/ui-prototype.html) : lecture publique, édition après connexion
  inscription.php    inscription publique des bénévoles (mobile d'abord)
  contact.php        page publique de contact de l'événement (?event=<id>)
  assets/app.css     styles communs aux deux pages
  assets/rte.js      éditeur de texte riche partagé (page contact, encadré d'appel)
  assets/i18n.js     traductions (brezhoneg / français / English), dates en breton, sélecteur de langue
  api.php            API JSON
  config.php         mot de passe admin (hash), DSN, nom de l'appli
  library/db.php         connexion PDO + création du schéma si absent
  library/html.php       nettoyage du texte riche (liste blanche de balises)
  library/tables.php     liste blanche des colonnes, camelCase <-> snake_case, span() côté PHP
  library/auth.php       session + jeton CSRF
  data/              regie.sqlite (+ .htaccess, index.php)
  export.php         export CSV (bénévoles × créneaux) et sauvegarde JSON complète
  import.php         import d'une sauvegarde JSON (sert aussi à changer d'hébergeur ou de SGBD)
  permalink.md       structure des liens permanents (créneau, poste, bénévole)
  database/schema.sql, seed_demo.sql, seed_kalanna2026.sql, seed_kalanna2026.json
```

## API (proposition)

- `GET  api.php?action=all` → `{admin, events, postes, creneaux, benevoles, affectations}` (la même forme que celle qu'attend l'interface). Public ; champs privés retirés hors admin.
- `POST api.php` avec `{action: create|update|delete, table, id?, data}` et l'en-tête `X-CSRF-Token` : **admin uniquement**
- `POST api.php` avec `{action: signup, creneauId, prenom, nom, tel, email, remarques?}` et l'en-tête `X-CSRF-Token` : public. L'insertion est conditionnelle (`INSERT … SELECT … WHERE nb < besoin`) pour ne jamais dépasser le besoin. Il y a un champ piège `website` et une limite de 20 inscriptions par heure et par session.
- `POST api.php` avec `{action: signup_resp, responsableId, prenom, nom, tel, email, remarques?}` et l'en-tête `X-CSRF-Token` : public. S'inscrit sur un poste de responsable où il reste de la place (insertion conditionnelle, `besoin` jamais dépassé) ; mêmes contrôles et même limite que `signup`.
- `POST api.php` avec `{action: lookup, q}` et l'en-tête `X-CSRF-Token` : public. Renvoie `{ids}` des bénévoles dont l'e-mail ou le téléphone correspond exactement à `q`.
- `POST api.php` avec `{action: login, password}` / `{action: logout}`
- `export.php` (CSV et sauvegarde JSON) : admin uniquement
- Tables autorisées : `events, postes, creneaux, benevoles, affectations, responsables, responsable_creneaux, responsable_personnes`. Colonnes en liste blanche pour chaque table.
- L'interface utilise du camelCase (`eventId`, `posteId`, `creneauId`, `benevoleId`, `sheetUrl`). La base utilise du snake_case. Faire la conversion dans l'API.
- Pour remplacer le temps réel du prototype (`onSnapshot`), l'interface recharge `action=all` toutes les 15 à 20 s et après chaque écriture.

## Modèle de données — voir `database/schema.sql`

Les tables sont `events` → `postes` → `creneaux` → `affectations` ← `benevoles`, et `events` → `responsables` → `responsable_creneaux` ← `creneaux` ; `responsables` → `responsable_personnes` ← `benevoles`. Les suppressions sont en `ON DELETE CASCADE` : supprimer un poste supprime ses créneaux et leurs affectations.

## Règles métier (déjà codées dans le prototype, à conserver)

1. **Règle de nuit** : un créneau qui commence avant 06:00 appartient à la nuit du jour indiqué (`jour=2026-12-31, 00:00–05:00` = nuit du 31 au 1er). Si fin < début, le créneau passe minuit. Voir `span()` dans le prototype.
2. **Remplissage** : on compte les affectations dont le statut n'est pas `absent`, puis on compare au `besoin`. États : complet (vert), partiel (orange), vide (rouge).
3. **Chevauchements** : on signale un bénévole placé sur deux créneaux qui se recouvrent (les absents sont ignorés). Dans la liste des candidats à affecter, les bénévoles déjà occupés sont grisés.
4. **Candidats** : ils sont triés par nombre d'heures déjà planifiées, du plus petit au plus grand, pour équilibrer la charge.
5. **Pointage le jour J** : chaque affectation a un statut `prevu` / `present` / `absent`.
6. **Planning** : c'est l'onglet affiché à l'ouverture. En haut, « Retrouver une inscription » cherche un bénévole et affiche ses créneaux de l'événement : par prénom/nom (partiel, sans accents) côté navigateur (hors admin, dès qu'il y a au moins deux mots, un mot peut correspondre à l'initiale du nom : « marie dupont » trouve « Marie D. »), ou par e-mail/téléphone. Hors admin, l'e-mail et le téléphone ne sont pas dans les données : `POST {action: lookup, q}` cherche la **valeur exacte** (téléphone comparé sur les 9 derniers chiffres) et ne renvoie que des ids, avec une limite de 30 recherches par heure et par session. À côté (les deux champs côte à côte, l'un sous l'autre sur téléphone ; résultats sous les deux champs sur toute la largeur, ou sous leur champ avec `PSEARCH_WIDTH = 'half'` dans `index.php`), « Retrouver un créneau » cherche les créneaux de l'événement par poste, responsable, jour ou heure (« bar 31 22h », « 22:00 » accepté) ; tous les résultats, regroupés par jour, puis par poste (sous-titre, postes en 2 ou 3 colonnes sur ordinateur) avec une ligne par créneau en retrait et son remplissage ; un clic ouvre le créneau. Sans `?day=`, il s'ouvre sur le jour qui compte le plus de créneaux. Deux vues au choix, mémorisées (`rb.view`) : **Grille** (frise horaire, seulement les postes qui ont des créneaux ce jour-là) et **Liste** (créneaux regroupés par poste, comme sur `inscription.php`), qui est la vue par défaut sur téléphone.
7. **Encadré « Envie de donner un coup de main ? »** (onglets Planning et Tableau de bord, visible par tous, organisateurs compris, avec le bouton « S'inscrire comme bénévole ») : texte riche modifiable sur place par les organisateurs (bouton « Modifier », même éditeur que la page contact), stocké par événement et par langue dans `events.appel`, `appel_br`, `appel_en` (HTML nettoyé par `library/html.php` ; une ancienne valeur en texte brut s'affiche avec la 1re ligne en gras). Si la version de la langue est vide, on prend la version française, puis le texte par défaut traduit.
8. **Annuaire des bénévoles** (onglet `bens`) : colonnes triables en cliquant l'en-tête (nom, créneaux, heures, date d'inscription ; tri mémorisé dans `rb.bsort`), par défaut les inscriptions les plus récentes en premier. La date d'inscription est celle de la dernière affectation sur l'événement, sinon celle de la fiche. Le **T-shirt est masqué pour l'instant** (colonne `taille` conservée en base, retirée de l'interface et du CSV).
9. **Déroulé de la soirée** : c'est le champ texte `events.deroule`, avec une ligne par étape au format `HH:MM Libellé`.
10. **Responsables** (onglet `resps`, public, entre Bénévoles et Postes & créneaux) : un **poste de responsable** par ligne de `responsables` (par événement : `titre` (vide = « Responsable »), `besoin` = nombre de personnes voulues, description, `fiche_url` = lien vers la fiche de poste, http(s) seulement). Il est lié aux créneaux par `responsable_creneaux` (plusieurs créneaux par poste, plusieurs postes possibles par créneau) et a **plusieurs personnes** dans `responsable_personnes` : un nom saisi par les organisateurs avec son téléphone et son e-mail facultatifs (nom public en entier ; tél/e-mail masqués hors admin par `fetch_all()`, comme ceux des bénévoles) ou un bénévole inscrit (nom, tél et e-mail pris sur sa fiche, masqués pour le public ; supprimer le bénévole libère la place). Chaque personne est affichée avec ses contacts (icônes ☎/✉). Remplissage affiché comme un créneau (`n/besoin`). Dans le formulaire organisateurs : une personne par ligne au format « nom, téléphone, e-mail » (tél et e-mail reconnus dans n'importe quel ordre, `lineToPers()`), bénévoles déjà inscrits (décocher pour retirer), « + Ajouter un bénévole », créneaux cochés. `create|update` de `responsables` accepte `data.creneauIds` (liste complète, remplace les liens) et `data.personnes = {remove: [ids], add: [{nom, tel?, email?}|{benevoleId}]}` (en différentiel, pour ne jamais effacer une inscription publique faite pendant l'édition), dans la même transaction (`set_resp_creneaux()`, `set_resp_personnes()` dans `api.php`). **Appel public** : tant qu'il reste de la place, le poste affiche « Je me propose » et apparaît sur `inscription.php` (section « Responsables recherchés », `#r-<id>`) ; on s'y inscrit avec la même identité que pour un créneau : `POST {action: signup_resp, …}` crée ou réutilise la fiche (code partagé avec `signup` : `signup_person()`, `find_benevole()`, `save_benevole()`, `add_remarque()`), insertion conditionnelle qui ne dépasse jamais `besoin`, refus si la personne y est déjà. Prendre un poste de responsable n'inscrit pas sur ses créneaux. Les responsables apparaissent aussi dans le tiroir du créneau, la fiche du bénévole et « Retrouver un créneau ». Lien direct : `&tab=resps&responsable=<id>`. `migrate()` crée les tables sur les bases existantes et convertit les 1res versions (une seule personne par responsable : colonnes `nom`, `benevole_id`).
11. **Couleurs des onglets** : Bénévoles et Responsables en jaune (`.t-yellow`, `--accent`), Planning et Postes & créneaux en blanc pur (`.t-white`), sur les trois pages.

## Données à importer

Au premier lancement, la base est remplie avec `database/seed_demo.sql` (option `seed_file` de `config.php`) : un petit événement de démonstration en anglais (6 postes, 13 créneaux, dont un de nuit et un qui passe minuit, sans bénévoles).

`database/seed_kalanna2026.sql` (à choisir dans `seed_file`) contient 1 événement, 24 postes (noms bretons et français) et 56 créneaux. Aucun bénévole n'y figure encore.
Ce jeu de données a été vérifié : le schéma et le seed se chargent sans erreur, et les cascades fonctionnent.
Points signalés comme **à vérifier avec l'organisation** (voir les notes « à préciser » dans les créneaux) : les horaires de l'installation du 29/12, de la préparation des crêpes à Quimperlé et de l'hébergement, l'effectif de la laverie, et les effectifs de la régie de 20h à 0h (2 ou 3 ?). Les créneaux qui finissent à « Fin » ont été fixés à 05:00, et ceux du bar à 04:45.

## Étapes proposées

1. `library/db.php` + `config.php` : connexion, pragmas, création du schéma, import du seed au premier lancement (optionnel).
2. `api.php` : lecture/écriture avec liste blanche, conversion camelCase ↔ snake_case, CSRF.
3. `index.php` : reprendre `reference/ui-prototype.html`, remplacer `claude.use('db')` et `onSnapshot` par `fetch` + rafraîchissement périodique, remplacer `claude.use('downloads')` par un lien vers `export.php`.
4. Connexion par mot de passe partagé pour les organisateurs (`password_hash` dans `config.php`).
5. Export/import JSON.
6. ✅ Page publique d'inscription des bénévoles (`inscription.php`).

Étapes 1 à 4 et 6 faites ; il reste `import.php` (étape 5).

## Questions tranchées

- Page d'inscription publique : **oui**.
- Comptes : **un seul mot de passe partagé** pour administrer. La consultation est publique et doit marcher sur mobile.
