# Choix techniques — modèle réutilisable

Ce document décrit la pile technique de la Régie Bénévoles et les raisons de chaque choix, pour servir de point de départ à de futurs projets du même genre : petits outils associatifs, quelques dizaines à quelques centaines d'utilisateurs, hébergés sur un mutualisé, maintenus par des bénévoles.

Le contexte propre à ce projet (règles métier, données) est dans `CLAUDE.md`.

## En une phrase

**PHP 8 + SQLite (PDO) + HTML/CSS/JS vanilla, sans framework, sans Composer, sans build.** Un dossier qu'on dépose par FTP et qui marche.

## Pourquoi cette pile

| Critère | Conséquence |
|---|---|
| Hébergement le moins cher possible (mutualisé OVH, o2switch, Infomaniak…) | PHP est partout, rien à installer côté serveur. |
| Changer d'hébergeur sans douleur | SQLite : la base est un fichier. Migrer = copier le dossier. |
| Maintenance par des non-spécialistes, sur plusieurs années | Aucune dépendance à mettre à jour, aucun `npm install`, aucune étape de build qui casse dans 3 ans. |
| Pas de dépendance à un service externe | Pas de Firebase/Supabase (quotas, mise en pause des projets gratuits, changement de conditions). |
| Trafic modeste mais pics ponctuels (soir de l'événement) | SQLite en mode WAL tient largement quelques écritures par seconde. |

### Alternatives écartées

- **Fichiers JSON** : écritures concurrentes risquées (deux inscriptions simultanées = données perdues).
- **MySQL** : création de base, identifiants, export/import à chaque changement d'hébergeur. On garde la porte ouverte (DSN dans `config.php`, SQL standard), mais on ne l'utilise pas par défaut.
- **Supabase / Firebase** : pratique pour prototyper, mais dépendance externe et pause des projets gratuits inactifs.
- **Framework PHP (Laravel, Symfony)** ou **front JS (React, Vue)** : disproportionné pour quelques écrans, et impose Composer/npm et un build.

### Quand NE PAS reprendre cette pile

- Plusieurs serveurs web derrière un répartiteur (SQLite = un seul serveur).
- Écritures très fréquentes et simultanées (plus de quelques dizaines par seconde).
- Besoin de vrais comptes utilisateurs avec rôles fins, récupération de mot de passe, etc.
- Interface très riche et très interactive (éditeur collaboratif, temps réel strict).

## Arborescence type

```
app/
  index.php          page principale (HTML + JS inline, lit l'API)
  autre-page.php     pages publiques secondaires
  api.php            API JSON unique (?action=…)
  export.php         export CSV / sauvegarde JSON (admin)
  config.php         nom de l'appli, hash du mot de passe admin, DSN
  lib/db.php         connexion PDO, pragmas, création du schéma, migrations
  lib/auth.php       session, connexion, jeton CSRF
  lib/tables.php     liste blanche tables/colonnes, camelCase <-> snake_case
  lib/html.php       nettoyage du HTML riche (si éditeur)
  assets/app.css     styles communs
  assets/i18n.js     traductions (si multilingue)
  database/schema.sql, seed_*.sql
  data/              la base SQLite (+ .htaccess "Require all denied", index.php vide)
```

Règles :
- **Chemins relatifs partout** : l'app doit marcher dans un sous-dossier (`https://asso.bzh/regie/`).
- Chaque page PHP est autonome : un peu de PHP en tête (session, données de démarrage), puis HTML + `<script>`. Pas de moteur de gabarits.

## Base de données : SQLite via PDO

### Connexion

À chaque connexion :

```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
```

- `foreign_keys` : SQLite ne les applique pas par défaut ; indispensable pour les `ON DELETE CASCADE`.
- `journal_mode = WAL` : lectures pendant les écritures, meilleure tenue en charge.
- `busy_timeout` : attend au lieu d'échouer immédiatement si la base est verrouillée.

### Création et évolution du schéma

- Base absente → création automatique depuis `database/schema.sql` (+ jeu de données initial en option). Aucune étape d'installation.
- Évolution → une fonction `migrate()` idempotente, appelée à chaque connexion, qui ajoute les colonnes manquantes (`PRAGMA table_info` puis `ALTER TABLE … ADD COLUMN`). Les bases existantes se mettent à jour seules.
- `data/` non inscriptible → message d'erreur clair affiché à l'utilisateur, pas une page blanche.

### Conventions

- Identifiants texte générés côté serveur (`bin2hex(random_bytes(…))`), dates en ISO 8601 texte.
- SQL standard (pas de spécificités SQLite inutiles) pour pouvoir passer à MySQL si besoin.
- Relations en cascade (`ON DELETE CASCADE`) : supprimer un parent nettoie les enfants, pas de code applicatif pour ça.
- Contraintes métier sensibles à la concurrence **dans le SQL**, pas en PHP. Exemple : ne jamais dépasser le nombre de places.

```sql
INSERT INTO affectations (id, creneau_id, benevole_id, statut)
SELECT ?, ?, ?, 'prevu'
WHERE (SELECT COUNT(*) FROM affectations WHERE creneau_id = ? AND statut <> 'absent')
      < (SELECT besoin FROM creneaux WHERE id = ?);
```

### Sauvegarde

Un `export.php` (admin) qui produit une sauvegarde JSON complète et un CSV lisible dans un tableur. La sauvegarde JSON sert aussi à changer d'hébergeur ou de SGBD.

## API JSON

Un seul point d'entrée `api.php` :

- `GET ?action=all` → tout l'état dont l'interface a besoin, en une requête. Suffisant tant que les données tiennent en quelques centaines de Ko.
- `POST {action: create|update|delete, table, id?, data}` → CRUD générique, **admin uniquement**.
- Actions métier dédiées pour ce qui est public ou délicat (`signup`…), avec leurs propres contrôles.
- `POST {action: login|logout}`.

Règles :
- **Liste blanche** des tables et des colonnes modifiables (`lib/tables.php`). Rien n'est interpolé dans le SQL sans passer par elle.
- **Requêtes préparées uniquement.**
- Le front parle en camelCase, la base en snake_case ; la conversion se fait dans l'API, à un seul endroit.
- Validation serveur de chaque champ (formats de date/heure, URL en `https://`, téléphone, longueur).
- Réponses `application/json; charset=utf-8`, `JSON_UNESCAPED_UNICODE`, codes HTTP significatifs (400, 403, 409…), message d'erreur lisible dans `error`.
- Transactions pour les écritures en plusieurs étapes, avec rollback dans le `catch`.
- Champs privés (téléphone, e-mail, notes) **retirés côté serveur** pour les visiteurs non connectés — jamais seulement masqués en CSS.

### « Temps réel » sans WebSocket

Le front recharge `action=all` toutes les 15 à 20 s et après chaque écriture. Simple, robuste, compatible avec tout hébergement. Suffisant pour quelques organisateurs qui travaillent en même temps.

## Sécurité

- **Un mot de passe partagé** pour les organisateurs, stocké en `password_hash()` dans `config.php` (jamais en clair). Hash vide = personne n'est admin.
  Générer un hash : `php -r 'echo password_hash("motdepasse", PASSWORD_DEFAULT), PHP_EOL;'`
- Session PHP avec cookie nommé, `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS ; `session_regenerate_id()` à la connexion ; `sleep(1)` sur mot de passe faux.
- **CSRF** : jeton en session, transmis dans l'en-tête `X-CSRF-Token` de chaque `POST`, vérifié avec `hash_equals`.
- **Formulaires publics** : champ piège (`website`, caché) + limite par session (ex. 20 inscriptions/heure).
- `data/` inaccessible depuis le web (`.htaccess` `Require all denied` + fallback `Deny from all`, `index.php` vide).
- Côté front, tout texte venant de la base est échappé avant insertion dans le DOM (`textContent` ou fonction `esc()`), jamais d'`innerHTML` brut.
- Texte riche (éditeur) : nettoyé **côté serveur** par liste blanche de balises (`DOMDocument`, voir `lib/html.php`) et de nouveau à l'affichage côté client (`DOMParser`). Liens limités à `https:`, `http:`, `mailto:`, `tel:`.

## Front-end

- **HTML/CSS/JS vanilla**, un `<script>` par page. Pas de bundler, pas de transpilation : le code qui tourne est le code qu'on lit.
- État dans un objet `S`, une fonction de rendu par vue (`rDash()`, `rPlan()`…) qui reconstruit le HTML de la zone concernée. Assez rapide pour quelques centaines d'éléments.
- Données de démarrage injectées par PHP (`window.BOOT = {csrf, admin, …}`) pour éviter une requête supplémentaire.
- **Mobile d'abord** pour les pages publiques ; mise en page CSS Grid/Flex, points de rupture simples (ex. 980 px et 560 px), pas de défilement horizontal.
- Couleurs en variables CSS sur `:root`, thème sombre via `prefers-color-scheme`.
- Polices Google Fonts, rien d'autre de chargé depuis l'extérieur.
- Préférences d'affichage (vue, langue) en `localStorage`, toujours dans un `try/catch`.
- Contexte dans l'URL (`?event=<id>`, `?lang=br`) : liens partageables, bouton retour qui marche.
- Suppressions : confirmation en deux clics sur le même bouton (`armed()`) plutôt que `confirm()`.

## Multilingue

- `assets/i18n.js`, sans bibliothèque. **Le texte français sert de clé** : `t('Enregistrer')`, `tn(n, 'créneau', 'créneaux')`, variables `t('{n} places', {n})`.
- Textes fixes du HTML : attribut `data-i18n="…"`, traduits au chargement.
- Langue : `?lang=` puis `localStorage`, français par défaut. Dates formatées à la main quand la langue n'est pas couverte par `Intl` (cas du breton).
- Les données saisies (noms, consignes) ne sont pas traduites ; les textes riches éditables ont une colonne par langue avec repli sur la langue par défaut.
- Prévoir un petit script (grep des `t('…')` / `tn(…)` comparé aux clés du dictionnaire) qui signale les chaînes non traduites et les clés inutilisées.
- Toute nouvelle chaîne doit être ajoutée au dictionnaire ; faire relire les traductions par un locuteur natif.

## Tester et développer en local

```sh
php -S 127.0.0.1:8000        # depuis le dossier de l'app
```

- **Toujours tester sur une copie** (`rsync` du dossier en excluant `data/*.sqlite*`) : ne jamais supprimer ni réinitialiser la base réelle, ne jamais écraser `config.php`.
- Désactiver l'opcache en CLI si on modifie `config.php` pendant les tests (`php -d opcache.enable_cli=0 -S …`).
- Tests d'API au `curl` (avec un fichier de cookies pour la session et le jeton CSRF).
- Captures d'écran avec Chrome headless (`--headless=new --screenshot --window-size=…`) pour vérifier la mise en page ordinateur et mobile.

## Mise en production

1. Déposer le dossier par FTP.
2. Vérifier que `data/` est inscriptible par PHP.
3. Mettre le hash du mot de passe dans `config.php`.
4. Ouvrir la page : la base se crée toute seule.
5. Vérifier que `https://…/data/regie.sqlite` renvoie bien 403.

Sauvegarder régulièrement via `export.php` (ou en copiant `data/` quand le site est calme).

## Check-list pour un nouveau projet

- [ ] Copier `lib/db.php`, `lib/auth.php`, `lib/tables.php`, `api.php` comme squelette.
- [ ] Écrire `database/schema.sql` (clés étrangères + cascades).
- [ ] Déclarer tables et colonnes dans la liste blanche.
- [ ] Définir ce qui est public, ce qui est admin, et quels champs sont privés.
- [ ] Mettre les contraintes de concurrence dans le SQL.
- [ ] Formulaires publics : champ piège + limite de débit.
- [ ] Pages publiques testées à 375 px de large.
- [ ] Export JSON/CSV avant le jour J.
- [ ] Documenter les décisions dans `CLAUDE.md`, la pile dans `tech.md`.
