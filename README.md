# YOUL VAT — Régie Bénévoles

LOGICIEL DE GESTION DES BENEVOLES POUR EVENEMENTS FESTIFS — volunteer scheduling for festivals and events.

![YOUL VAT volunteer management](https://ik.imagekit.io/breizhwave/cms/webmasterbulletin/volonteer-management.jpg)

Trilingue brezhoneg / français / English (`?lang=br|fr|en`).

PHP 8 + SQLite, no framework, no Composer, no build step: upload the folder by FTP and open it.
Copy `config.example.php` to `config.php` and set `admin_password_hash`
(`php -r 'echo password_hash("secret", PASSWORD_DEFAULT), PHP_EOL;'`).

Technical choices: [`tech.md`](tech.md) (French). This stack is also packaged as a Claude skill: [breizhwave/wavedevsimpl](https://github.com/breizhwave/wavedevsimpl).
