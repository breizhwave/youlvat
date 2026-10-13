# YOUL VAT — Régie Bénévoles

LOGICIEL DE GESTION DES BENEVOLES POUR EVENEMENTS FESTIFS — volunteer scheduling for festivals and events.

![YOUL VAT volunteer management](https://ik.imagekit.io/breizhwave/cms/webmasterbulletin/volonteer-management.jpg)

Trilingue brezhoneg / français / English (`?lang=br|fr|en`).

PHP 8 + SQLite, no framework, no Composer, no build step: upload the folder by FTP and open it.
Copy `config.example.php` to `config.php` and set `admin_password_hash`
(`php -r 'echo password_hash("secret", PASSWORD_DEFAULT), PHP_EOL;'`).

Technical choices: [`tech.md`](tech.md) (French). This stack is also packaged as a Claude skill: [breizhwave/wavedevsimpl](https://github.com/breizhwave/wavedevsimpl).

## Database

Five SQLite tables (`database/schema.sql`), created automatically on first run, with a small
English demo event preloaded (`database/seed_demo.sql`). Set `seed_file` in `config.php` to
`seed_kalanna2026.sql` for the Fest-noz Vras Kalanna 2026, or `seed_on_create` to `false` to start empty.
Deleting an event, a poste or a créneau cascades down (`ON DELETE CASCADE`).

`benevoles` is a global directory shared across events; volunteers are linked to an event only
through `affectations`.

**Schema diagram, rules, life cycle and how concurrent writes are handled: [`database.md`](database.md).**
