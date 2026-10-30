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
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        // WAL : les lectures ne bloquent pas les écritures. Sur un disque réseau, SQLite peut le refuser
        // sans erreur et garder son ancien mode : on le signale une fois dans le journal PHP.
        $mode = strtolower((string)$pdo->query('PRAGMA journal_mode = WAL')->fetchColumn());
        db_journal_mode($mode);
        if ($mode !== 'wal' && !is_file("$dir/.journal_warned")) {
            error_log("[regie] SQLite n'a pas pu passer en mode WAL (mode : $mode) : les lectures bloqueront les écritures.");
            @touch("$dir/.journal_warned");
        }
        if ($fresh) {
            $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
            if (!empty($cfg['seed_on_create'])) {
                // Événement de démonstration par défaut ; 'seed_kalanna2026.sql' pour le Fest-noz.
                $seed = basename($cfg['seed_file'] ?? 'seed_demo.sql');
                $pdo->exec(file_get_contents(__DIR__ . '/../database/' . $seed));
            }
        } else {
            migrate($pdo);
        }
    }
    return $pdo;
}

// Mode de journal réellement obtenu ('wal' attendu), visible des organisateurs dans api.php?action=all.
function db_journal_mode(?string $set = null): ?string
{
    static $mode = null;
    if ($set !== null) $mode = $set;
    return $mode;
}

// Transaction qui prend le verrou d'écriture dès le début (BEGIN IMMEDIATE) : une transaction « différée »
// qui lit avant d'écrire échoue aussitôt en SQLITE_BUSY si une autre écriture passe entre-temps,
// sans attendre busy_timeout. Même chose si une requête de lecture a encore un curseur ouvert sur la connexion
// (fetch() d'une seule ligne) : appeler closeCursor() avant tx(). tx() annule tout si $fn lève une exception (ou sort via fail()).
function tx(PDO $pdo, callable $fn)
{
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $sqlite ? $pdo->exec('BEGIN IMMEDIATE') : $pdo->beginTransaction();
    $done = false;
    try {
        $r = $fn();
        $sqlite ? $pdo->exec('COMMIT') : $pdo->commit();
        $done = true;
        return $r;
    } finally {
        if (!$done) {
            try { $sqlite ? $pdo->exec('ROLLBACK') : $pdo->rollBack(); } catch (PDOException $e) {}
        }
    }
}

// « database is locked » : SQLITE_BUSY (5) ou SQLITE_LOCKED (6), après expiration de busy_timeout.
function is_busy(PDOException $e): bool
{
    $code = $e->errorInfo[1] ?? null;
    return $code === 5 || $code === 6 || stripos($e->getMessage(), 'database is locked') !== false;
}

// Colonnes ajoutées après la première version : on les crée sur les bases existantes.
function migrate(PDO $pdo): void
{
    $cols = array_column($pdo->query('PRAGMA table_info(events)')->fetchAll(), 'name');
    foreach (['image_url', 'contact_tel', 'contact_infos', 'contact_html', 'contact_html_br', 'contact_html_en', 'appel', 'appel_br', 'appel_en'] as $c) {
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE events ADD COLUMN $c TEXT");
    }
    // tables ajoutées depuis (responsables…) : le schéma n'utilise que des CREATE … IF NOT EXISTS
    $n = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'responsable_creneaux'")->fetchColumn();
    if (!$n) $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    // 1res versions de responsables (une seule personne : colonnes nom, puis benevole_id) : table reconstruite sans elles,
    // et chaque personne passe dans responsable_personnes. SQLite ne sait pas retirer une colonne NOT NULL.
    // foreign_keys coupé le temps de l'opération, sinon DROP TABLE effacerait les liens avec les créneaux.
    $rcols = fn() => array_column($pdo->query('PRAGMA table_info(responsables)')->fetchAll(), 'name');
    if (in_array('nom', $rcols(), true)) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            tx($pdo, function () use ($pdo, $rcols) {
                $old = $rcols();
                if (!in_array('nom', $old, true)) return; // fait entre-temps
                $ben = in_array('benevole_id', $old, true) ? 'benevole_id' : 'NULL';
                $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql')); // crée responsable_personnes
                $pdo->exec("INSERT INTO responsable_personnes (id, event_id, responsable_id, nom, benevole_id)
                            SELECT lower(hex(randomblob(8))), event_id, id, NULLIF(TRIM(nom), ''), $ben FROM responsables
                             WHERE COALESCE(TRIM(nom), '') <> '' OR $ben IS NOT NULL");
                // ordre recommandé par SQLite : nouvelle table, copie, suppression de l'ancienne, puis renommage de la nouvelle
                // (renommer l'ancienne ferait pointer les clés étrangères des autres tables vers elle)
                $pdo->exec("CREATE TABLE responsables_v3 (
                    id TEXT PRIMARY KEY,
                    event_id TEXT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                    titre TEXT,
                    besoin INTEGER NOT NULL DEFAULT 1 CHECK (besoin >= 1),
                    description TEXT,
                    fiche_url TEXT,
                    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
                    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')))");
                $pdo->exec('INSERT INTO responsables_v3 (id, event_id, description, fiche_url, created_at, updated_at)
                            SELECT id, event_id, description, fiche_url, created_at, updated_at FROM responsables');
                $pdo->exec('DROP TABLE responsables');
                $pdo->exec('ALTER TABLE responsables_v3 RENAME TO responsables');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_resp_event ON responsables(event_id)');
            });
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
    $pcols = array_column($pdo->query('PRAGMA table_info(responsable_personnes)')->fetchAll(), 'name');
    foreach (['tel', 'email'] as $c) {
        if (!in_array($c, $pcols, true)) $pdo->exec("ALTER TABLE responsable_personnes ADD COLUMN $c TEXT");
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
