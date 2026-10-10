<?php
// Connexion PDO + création du schéma au premier lancement.

function config(): array
{
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config.php';
    return $cfg;
}

class DbSetupError extends RuntimeException {}

// Interdit le téléchargement direct du dossier (base SQLite) : recrée .htaccess et index.php s'ils manquent.
function protect_dir(string $dir): void
{
    $ht = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
    if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", $ht);
    if (!is_file("$dir/index.php")) @file_put_contents("$dir/index.php", "<?php\n");
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = config();
    $isSqlite = str_starts_with($cfg['dsn'], 'sqlite:');
    $fresh = false;

    if ($isSqlite) {
        if (!extension_loaded('pdo_sqlite')) {
            throw new DbSetupError("L'extension PHP pdo_sqlite n'est pas activée sur cet hébergement.");
        }
        $file = substr($cfg['dsn'], 7);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new DbSetupError("Impossible de créer le dossier data/. Créez-le et rendez-le inscriptible (chmod 775).");
        }
        if (!is_writable($dir)) {
            throw new DbSetupError("Le dossier data/ n'est pas inscriptible par PHP. Donnez-lui les droits d'écriture (chmod 775 ou 777 selon l'hébergeur).");
        }
        $fresh = !file_exists($file) || filesize($file) === 0;
        protect_dir($dir);
    }

    $pdo = new PDO($cfg['dsn'], $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($isSqlite) {
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
        if ($fresh) {
            $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
            if (!empty($cfg['seed_on_create'])) {
                $pdo->exec(file_get_contents(__DIR__ . '/../database/seed_kalanna2026.sql'));
            }
        } else {
            migrate($pdo);
        }
    }
    return $pdo;
}

// Colonnes ajoutées après la première version : on les crée sur les bases existantes.
function migrate(PDO $pdo): void
{
    $cols = array_column($pdo->query('PRAGMA table_info(events)')->fetchAll(), 'name');
    foreach (['image_url', 'contact_tel', 'contact_infos', 'contact_html', 'contact_html_br', 'contact_html_en'] as $c) {
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE events ADD COLUMN $c TEXT");
    }
}

function new_id(): string
{
    return bin2hex(random_bytes(8));
}

function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}
