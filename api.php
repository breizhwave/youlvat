<?php
// API JSON de la régie.
//   GET  api.php?action=all                 -> {events, postes, creneaux, benevoles, affectations}
//        public ; sans session admin, tel/email/notes des bénévoles sont retirés.
//   POST api.php {action: create|update|delete, table, id?, data}   admin (en-tête X-CSRF-Token)
//   POST api.php {action: delete_day, eventId, jour}                 admin : supprime les créneaux du jour
//   POST api.php {action: signup, creneauId, prenom, nom, tel?, email?}  public (en-tête X-CSRF-Token)
//   POST api.php {action: login, password} / {action: logout}

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/tables.php';
require_once __DIR__ . '/lib/html.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $msg, int $code = 400, string $err = 'bad_request'): void
{
    out(['ok' => false, 'error' => $err, 'message' => $msg], $code);
}

// Colonnes autorisées uniquement, '' -> NULL, entiers castés.
function clean(string $table, array $data): array
{
    $def = TABLES[$table];
    $out = [];
    foreach ($def['cols'] as $k) {
        if (!array_key_exists($k, $data)) continue;
        $v = $data[$k];
        if (is_string($v)) $v = trim($v);
        if ($v === '' || $v === null) $v = null;
        elseif (in_array($k, $def['int'], true)) $v = (int)$v;
        elseif (!is_scalar($v)) fail("Valeur invalide pour « $k ».");
        else $v = (string)$v;
        $out[$k] = $v;
    }
    // texte riche de la page contact : balises et liens en liste blanche
    foreach (['contactHtml', 'contactHtmlBr'] as $k) {
        if (!isset($out[$k])) continue;
        if (strlen($out[$k]) > 50000) fail('Texte trop long.');
        $out[$k] = sanitize_html($out[$k]) ?: null;
    }
    return $out;
}

function validate(string $table, array $d, bool $isCreate): void
{
    $def = TABLES[$table];
    foreach ($def['req'] as $k) {
        if (($isCreate || array_key_exists($k, $d)) && ($d[$k] ?? null) === null) {
            fail("Champ obligatoire manquant : $k.");
        }
    }
    $date = '/^\d{4}-\d{2}-\d{2}$/';
    $time = '/^([01]\d|2[0-3]):[0-5]\d$/';
    foreach (['debut', 'fin', 'jour'] as $k) {
        if (!isset($d[$k])) continue;
        $re = ($table === 'creneaux' && $k !== 'jour') ? $time : $date;
        if (!preg_match($re, $d[$k])) fail("Format invalide pour « $k ».");
    }
    if ($table === 'events' && isset($d['debut'], $d['fin']) && $d['fin'] < $d['debut']) fail('Le dernier jour doit suivre le premier.');
    if ($table === 'creneaux' && isset($d['debut'], $d['fin']) && $d['debut'] === $d['fin']) fail('Le début et la fin doivent être différents.');
    if (isset($d['besoin']) && $d['besoin'] < 1) fail('Il faut au moins 1 bénévole.');
    if (isset($d['couleur']) && ($d['couleur'] < 0 || $d['couleur'] > 7)) fail('Couleur invalide.');
    if (isset($d['taille']) && !in_array($d['taille'], ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true)) fail('Taille invalide.');
    if (isset($d['statut']) && !in_array($d['statut'], ['prevu', 'present', 'absent'], true)) fail('Statut invalide.');
    if (isset($d['sheetUrl']) && !preg_match('~^https://~', $d['sheetUrl'])) fail('Le lien du Google Sheet doit commencer par https://');
    if (isset($d['imageUrl']) && !preg_match('~^https://\S+$~', $d['imageUrl'])) fail("L'adresse de l'image doit commencer par https://");
    if (isset($d['contactTel']) && !preg_match('/^\+?[0-9 .()-]{6,25}$/', $d['contactTel'])) fail('Numéro de téléphone invalide.');
}

function get_row(PDO $pdo, string $table, string $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? row_to_api($r) : null;
}

function clean_name($v): string
{
    $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
    if ($v === '' || mb_strlen($v) > 60 || preg_match('/[\p{C}<>]/u', $v)) fail('Indiquez un prénom et un nom valides (60 caractères max).');
    return $v;
}

// Inscription publique : prénom + nom, sans compte. Réutilise la fiche si le nom existe déjà.
function signup(PDO $pdo, array $b): void
{
    if (!empty($b['website'])) out(['ok' => true]); // champ piège anti-robots

    $now = time();
    $recent = array_filter($_SESSION['signups'] ?? [], fn($t) => $t > $now - 3600);
    if (count($recent) >= 20) fail('Trop d’inscriptions depuis cet appareil. Contactez l’organisation.', 429, 'rate');

    $prenom = clean_name($b['prenom'] ?? '');
    $nom = clean_name($b['nom'] ?? '');
    // Contact facultatif, visible des seuls organisateurs.
    $tel = trim((string)($b['tel'] ?? '')) ?: null;
    $email = trim((string)($b['email'] ?? '')) ?: null;
    if ($tel !== null && !preg_match('/^\+?[0-9 .()-]{6,25}$/', $tel)) fail('Numéro de téléphone invalide.');
    if ($email !== null && (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) fail('Adresse e-mail invalide.');
    $st = $pdo->prepare('SELECT c.*, p.nom AS poste FROM creneaux c JOIN postes p ON p.id = c.poste_id WHERE c.id = ?');
    $st->execute([(string)($b['creneauId'] ?? '')]);
    $c = $st->fetch();
    if (!$c) fail('Ce créneau n’existe plus.', 404, 'not_found');

    $key = fn($s) => mb_strtolower($s, 'UTF-8');
    $bid = null;
    foreach ($pdo->query('SELECT id, prenom, nom FROM benevoles') as $r) {
        if ($key($r['prenom']) === $key($prenom) && $key($r['nom']) === $key($nom)) { $bid = $r['id']; break; }
    }

    if ($bid) {
        $st = $pdo->prepare("SELECT c.*, p.nom AS poste FROM affectations a JOIN creneaux c ON c.id = a.creneau_id
                               JOIN postes p ON p.id = c.poste_id WHERE a.benevole_id = ? AND a.statut <> 'absent'");
        $st->execute([$bid]);
        $mine = creneau_span($c);
        foreach ($st as $o) {
            if ($o['id'] === $c['id']) fail('Vous êtes déjà inscrit·e sur ce créneau.', 409, 'conflict');
            $s = creneau_span($o);
            if ($s[0] < $mine[1] && $mine[0] < $s[1]) {
                // Les champs poste/debut/fin permettent à l'interface de traduire le message.
                out(['ok' => false, 'error' => 'overlap', 'poste' => $o['poste'], 'debut' => $o['debut'], 'fin' => $o['fin'],
                     'message' => "Vous êtes déjà inscrit·e à la même heure : {$o['poste']} ({$o['debut']}–{$o['fin']})."], 409);
            }
        }
    }

    $pdo->beginTransaction();
    if (!$bid) {
        $bid = new_id();
        $pdo->prepare('INSERT INTO benevoles (id, prenom, nom, tel, email) VALUES (?, ?, ?, ?, ?)')->execute([$bid, $prenom, $nom, $tel, $email]);
    } elseif ($tel !== null || $email !== null) {
        // Fiche existante : on complète seulement les champs vides, sans jamais écraser un contact déjà saisi.
        $pdo->prepare('UPDATE benevoles SET tel = COALESCE(tel, ?), email = COALESCE(email, ?), updated_at = ? WHERE id = ?')
            ->execute([$tel, $email, now_iso(), $bid]);
    }
    // Insertion conditionnelle en une seule requête : impossible de dépasser le besoin, même à plusieurs en même temps.
    $aid = new_id();
    $st = $pdo->prepare("INSERT INTO affectations (id, event_id, creneau_id, benevole_id, statut)
        SELECT ?, ?, ?, ?, 'prevu'
         WHERE (SELECT COUNT(*) FROM affectations WHERE creneau_id = ? AND statut <> 'absent')
             < (SELECT besoin FROM creneaux WHERE id = ?)");
    $st->execute([$aid, $c['event_id'], $c['id'], $bid, $c['id'], $c['id']]);
    if (!$st->rowCount()) {
        $pdo->rollBack();
        fail('Ce créneau est complet. Choisissez-en un autre.', 409, 'full');
    }
    $pdo->commit();

    $recent[] = $now;
    $_SESSION['signups'] = array_values($recent);
    out(['ok' => true, 'benevoleId' => $bid, 'id' => $aid], 201);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $body = [];
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($body)) fail('JSON invalide.');
        if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) fail('Session expirée : rechargez la page.', 403, 'csrf');
    }
    $action = $method === 'POST' ? ($body['action'] ?? '') : ($_GET['action'] ?? '');

    if ($action === 'login') {
        if (!auth_enabled()) fail("Aucun mot de passe administrateur n'est configuré (voir config.php).", 401, 'auth');
        if (!login((string)($body['password'] ?? ''))) fail('Mot de passe incorrect.', 401, 'auth');
        out(['ok' => true]);
    }
    if ($action === 'logout') {
        logout();
        out(['ok' => true]);
    }

    $pdo = db();

    if ($method === 'GET' && $action === 'all') {
        out(['ok' => true, 'admin' => is_admin()] + fetch_all($pdo, is_admin()));
    }

    if ($method === 'POST' && $action === 'signup') signup($pdo, $body);

    if (!is_admin()) fail('Réservé aux organisateurs : connectez-vous.', 401, 'auth');
    if ($method !== 'POST') fail('Action inconnue.', 404, 'not_found');

    // Suppression de tous les créneaux d'un jour (les affectations suivent par cascade).
    if ($action === 'delete_day') {
        $jour = (string)($body['jour'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) fail('Jour invalide.');
        $st = $pdo->prepare('DELETE FROM creneaux WHERE event_id = ? AND jour = ?');
        $st->execute([(string)($body['eventId'] ?? ''), $jour]);
        out(['ok' => true, 'deleted' => $st->rowCount()]);
    }

    $table = (string)($body['table'] ?? '');
    if (!isset(TABLES[$table])) fail('Table non autorisée.');
    $id = isset($body['id']) ? (string)$body['id'] : null;
    $data = is_array($body['data'] ?? null) ? $body['data'] : [];

    switch ($action) {
        case 'create':
            $d = clean($table, $data);
            validate($table, $d, true);
            $newId = new_id();
            $cols = array_merge(['id'], array_map('snake', array_keys($d)));
            $sql = "INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $pdo->prepare($sql)->execute(array_merge([$newId], array_values($d)));
            out(['ok' => true, 'id' => $newId, 'row' => get_row($pdo, $table, $newId)], 201);

        case 'update':
            if (!$id) fail('Identifiant manquant.');
            $d = clean($table, $data);
            validate($table, $d, false);
            if (!$d) fail('Rien à modifier.');
            $set = implode(', ', array_map(fn($k) => snake($k) . ' = ?', array_keys($d)));
            $st = $pdo->prepare("UPDATE $table SET $set, updated_at = ? WHERE id = ?");
            $st->execute(array_merge(array_values($d), [now_iso(), $id]));
            if (!$st->rowCount()) fail('Élément introuvable (supprimé entre-temps ?).', 404, 'not_found');
            out(['ok' => true, 'row' => get_row($pdo, $table, $id)]);

        case 'delete':
            if (!$id) fail('Identifiant manquant.');
            $st = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $st->execute([$id]);
            out(['ok' => true, 'deleted' => $st->rowCount()]);

        default:
            fail('Action inconnue.', 404, 'not_found');
    }
} catch (DbSetupError $e) {
    fail($e->getMessage(), 500, 'setup');
} catch (PDOException $e) {
    if (db()->inTransaction()) db()->rollBack();
    $m = $e->getMessage();
    if (stripos($m, 'UNIQUE') !== false) fail('Ce bénévole est déjà sur ce créneau.', 409, 'conflict');
    if (stripos($m, 'FOREIGN KEY') !== false) fail('Élément lié introuvable (supprimé entre-temps ?).', 409, 'conflict');
    if (stripos($m, 'CHECK') !== false || stripos($m, 'NOT NULL') !== false) fail('Valeur refusée par la base.', 400);
    error_log('[regie] ' . $m);
    fail('Erreur de base de données.', 500, 'db');
}
