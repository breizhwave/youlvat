# Régie Bénévoles — contexte projet

Outil de planning des bénévoles pour le **Fest-noz Vras Kalanna** (Quimper, 29/12/2026 → 02/01/2027, soirée du 31/12).
Il est d'abord né sous forme de prototype claude.ai (voir `reference/ui-prototype.html`). **On le porte maintenant en PHP + SQLite** pour pouvoir l'héberger n'importe où.

## Décisions déjà prises (ne pas remettre en cause sans demander)

- **Stockage : SQLite via PDO (`pdo_sqlite`)**. Priorité absolue : la portabilité entre hébergeurs PHP mutualisés. Migrer = copier le dossier.
  - Rejeté : fichiers JSON (écritures concurrentes risquées le soir J), MySQL (trop de friction à chaque migration), Supabase (dépendance externe, pause des projets gratuits après 7 jours d'inactivité).
  - Écrire du SQL standard pour garder une porte de sortie vers MySQL (DSN dans `config.php`).
- **Données privées limitées** : les noms des bénévoles sont publics. Le téléphone et l'e-mail sont facultatifs (mais recommandés) et ne sont visibles que par les organisateurs. Les formulaires l'indiquent.
- **L'interface du prototype plaît** : la réutiliser telle quelle (HTML/CSS/JS vanilla, polices Google Fonts, thèmes clair/sombre). Seule la couche de données change.
- Le Google Sheet d'origine n'est plus affiché ni modifiable dans l'interface. La colonne `sheet_url` est conservée en base (données historiques).
- **Choix de l'événement par l'URL** : `index.php?event=<id>` et `inscription.php?event=<id>`. Sans paramètre (ou avec un id inconnu), on affiche seulement la liste des événements. Le sélecteur de l'en-tête recharge la page avec le bon `?event=`.
- **Image par événement** : `events.image_url` (https uniquement), affichée à côté du sélecteur et sur les cartes de la liste. La colonne est ajoutée automatiquement aux bases existantes (`migrate()` dans `lib/db.php`).
- **Consultation publique** : tout le monde voit le planning (`index.php`) sans compte, sur ordinateur comme sur téléphone. Sans session admin, l'API retire `tel`, `email` et `notes` des bénévoles.
- **Un seul compte partagé** pour administrer (mot de passe dans `config.php`). Pas de comptes par responsable de stand. Tant que `admin_password_hash` est vide, personne ne peut administrer.
- **Inscription publique** (`inscription.php`) : un bénévole choisit un créneau libre et donne son prénom + nom (et, s'il le souhaite, son téléphone et son e-mail), sans compte. Si le nom existe déjà (sans tenir compte de la casse), la fiche est réutilisée. Le tél/e-mail saisis complètent alors seulement les champs vides : ils n'écrasent jamais un contact existant. Refus si le créneau est complet, si la personne y est déjà ou si elle est prise à la même heure. Pas de désinscription en ligne : on contacte l'organisation.

- **Page contact** (`contact.php?event=<id>`, publique). Elle affiche l'e-mail (`events.contact`), le téléphone (`events.contact_tel`) et d'autres informations en texte libre, une par ligne (`events.contact_infos`, où les liens web et les e-mails deviennent cliquables). Ces champs se modifient dans le formulaire de l'événement. Elle est liée depuis l'onglet « Contact » du planning, l'en-tête de la page d'inscription et le tableau de bord. Ces coordonnées sont **publiques**, contrairement au tél/e-mail des bénévoles. Les organisateurs connectés ont un bouton « Modifier la page » : un éditeur intégré (`contenteditable` + `execCommand`, sans bibliothèque) pour le texte de la page, avec une version par langue (`events.contact_html` en français, `events.contact_html_br` en breton, `events.contact_html_en` en anglais ; si la version de la langue est vide, on affiche le français). Au collage, on ne garde que le texte brut. Le HTML est **nettoyé côté serveur** (`lib/html.php`, liste blanche : p, br, b/strong, i/em, u, a[href https/http/mailto/tel], ul/ol/li, h3/h4, blockquote) et de nouveau à l'affichage.
- **Trilingue brezhoneg / français / English** (`assets/i18n.js`). Tout texte d'interface passe par `t('texte français')` ou `tn(n,'singulier','pluriel')`, et le texte français sert de clé des dictionnaires breton (`BR`) et anglais (`EN`). Textes fixes du HTML : `data-i18n="…"`. Langue : `?lang=br|fr|en` (mémorisée dans `rb.lang`), français par défaut. Sélecteur à droite de « Organisateurs ». Les données (noms de postes, consignes, déroulé) ne sont pas traduites. **Toute nouvelle chaîne doit être ajoutée aux dictionnaires breton et anglais.** Les traductions sont à faire relire par un·e brittophone.

## Contraintes techniques

- PHP ≥ 8.0, **sans Composer ni framework**, sans étape de build. Un seul dossier à déposer par FTP.
- Chemins relatifs partout (l'app doit marcher dans un sous-dossier, ex. `https://asso.bzh/regie/`).
- La base est créée automatiquement au premier lancement à partir de `database/schema.sql`.
- Pour chaque connexion PDO : `PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;`
- Le dossier `data/` (qui contient `regie.sqlite`) est protégé par un `.htaccess` (`Require all denied` + fallback `Deny from all`) et un `index.php` vide. Si `data/` n'est pas inscriptible, l'app doit l'expliquer clairement.
- Requêtes préparées uniquement. Réponses JSON en UTF-8 (`JSON_UNESCAPED_UNICODE`).

## Arborescence visée

```
regie/
  index.php          interface (portée de reference/ui-prototype.html) : lecture publique, édition après connexion
  inscription.php    inscription publique des bénévoles (mobile d'abord)
  contact.php        page publique de contact de l'événement (?event=<id>)
  assets/app.css     styles communs aux deux pages
  assets/i18n.js     traductions (brezhoneg / français / English), dates en breton, sélecteur de langue
  api.php            API JSON
  config.php         mot de passe admin (hash), DSN, nom de l'appli
  lib/db.php         connexion PDO + création du schéma si absent
  lib/html.php       nettoyage du texte riche (liste blanche de balises)
  lib/tables.php     liste blanche des colonnes, camelCase <-> snake_case, span() côté PHP
  lib/auth.php       session + jeton CSRF
  data/              regie.sqlite (+ .htaccess, index.php)
  export.php         export CSV (bénévoles × créneaux) et sauvegarde JSON complète
  import.php         import d'une sauvegarde JSON (sert aussi à changer d'hébergeur ou de SGBD)
  database/schema.sql, seed_kalanna2026.sql, seed_kalanna2026.json
```

## API (proposition)

- `GET  api.php?action=all` → `{admin, events, postes, creneaux, benevoles, affectations}` (la même forme que celle qu'attend l'interface). Public ; champs privés retirés hors admin.
- `POST api.php` avec `{action: create|update|delete, table, id?, data}` et l'en-tête `X-CSRF-Token` : **admin uniquement**
- `POST api.php` avec `{action: signup, creneauId, prenom, nom}` et l'en-tête `X-CSRF-Token` : public. L'insertion est conditionnelle (`INSERT … SELECT … WHERE nb < besoin`) pour ne jamais dépasser le besoin. Il y a un champ piège `website` et une limite de 20 inscriptions par heure et par session.
- `POST api.php` avec `{action: login, password}` / `{action: logout}`
- `export.php` (CSV et sauvegarde JSON) : admin uniquement
- Tables autorisées : `events, postes, creneaux, benevoles, affectations`. Colonnes en liste blanche pour chaque table.
- L'interface utilise du camelCase (`eventId`, `posteId`, `creneauId`, `benevoleId`, `sheetUrl`). La base utilise du snake_case. Faire la conversion dans l'API.
- Pour remplacer le temps réel du prototype (`onSnapshot`), l'interface recharge `action=all` toutes les 15 à 20 s et après chaque écriture.

## Modèle de données — voir `database/schema.sql`

Les tables sont `events` → `postes` → `creneaux` → `affectations` ← `benevoles`. Les suppressions sont en `ON DELETE CASCADE` : supprimer un poste supprime ses créneaux et leurs affectations.

## Règles métier (déjà codées dans le prototype, à conserver)

1. **Règle de nuit** : un créneau qui commence avant 06:00 appartient à la nuit du jour indiqué (`jour=2026-12-31, 00:00–05:00` = nuit du 31 au 1er). Si fin < début, le créneau passe minuit. Voir `span()` dans le prototype.
2. **Remplissage** : on compte les affectations dont le statut n'est pas `absent`, puis on compare au `besoin`. États : complet (vert), partiel (orange), vide (rouge).
3. **Chevauchements** : on signale un bénévole placé sur deux créneaux qui se recouvrent (les absents sont ignorés). Dans la liste des candidats à affecter, les bénévoles déjà occupés sont grisés.
4. **Candidats** : ils sont triés par nombre d'heures déjà planifiées, du plus petit au plus grand, pour équilibrer la charge.
5. **Pointage le jour J** : chaque affectation a un statut `prevu` / `present` / `absent`.
6. **Planning** : l'onglet s'ouvre par défaut sur le jour qui compte le plus de créneaux. Deux vues au choix, mémorisées (`rb.view`) : **Grille** (frise horaire, seulement les postes qui ont des créneaux ce jour-là) et **Liste** (créneaux regroupés par poste, comme sur `inscription.php`), qui est la vue par défaut sur téléphone.
7. **Déroulé de la soirée** : c'est le champ texte `events.deroule`, avec une ligne par étape au format `HH:MM Libellé`.

## Données à importer

`database/seed_kalanna2026.sql` contient 1 événement, 24 postes (noms bretons et français) et 56 créneaux. Aucun bénévole n'y figure encore.
Ce jeu de données a été vérifié : le schéma et le seed se chargent sans erreur, et les cascades fonctionnent.
Points signalés comme **à vérifier avec l'organisation** (voir les notes « à préciser » dans les créneaux) : les horaires de l'installation du 29/12, de la préparation des crêpes à Quimperlé et de l'hébergement, l'effectif de la laverie, et les effectifs de la régie de 20h à 0h (2 ou 3 ?). Les créneaux qui finissent à « Fin » ont été fixés à 05:00, et ceux du bar à 04:45.

## Étapes proposées

1. `lib/db.php` + `config.php` : connexion, pragmas, création du schéma, import du seed au premier lancement (optionnel).
2. `api.php` : lecture/écriture avec liste blanche, conversion camelCase ↔ snake_case, CSRF.
3. `index.php` : reprendre `reference/ui-prototype.html`, remplacer `claude.use('db')` et `onSnapshot` par `fetch` + rafraîchissement périodique, remplacer `claude.use('downloads')` par un lien vers `export.php`.
4. Connexion par mot de passe partagé pour les organisateurs (`password_hash` dans `config.php`).
5. Export/import JSON.
6. ✅ Page publique d'inscription des bénévoles (`inscription.php`).

Étapes 1 à 4 et 6 faites ; il reste `import.php` (étape 5).

## Questions tranchées

- Page d'inscription publique : **oui**.
- Comptes : **un seul mot de passe partagé** pour administrer. La consultation est publique et doit marcher sur mobile.
