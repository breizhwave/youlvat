<?php
// API JSON de la régie.
//   GET  api.php?action=all                 -> {events, postes, creneaux, benevoles, affectations, responsables, responsable_creneaux}
//        public ; sans session admin, les notes des bénévoles sont retirées, le nom est réduit
//        à son initiale (« D. ******* »), l'e-mail à ses 2 premiers caractères, le tél à ses 2 premiers et 2 derniers chiffres.
//   POST api.php {action: create|update|delete, table, id?, data}   admin (en-tête X-CSRF-Token)
//        table responsables : data.creneauIds (liste complète) remplace ses liens avec les créneaux ;
//        data.personnes = {remove: [ids], add: [{nom, tel?, email?} | {benevoleId}]} modifie les personnes sur le poste
//   POST api.php {action: signup, creneauId, prenom, nom, tel, email, remarques?}  public (en-tête X-CSRF-Token)
//   POST api.php {action: signup_resp, responsableId, prenom, nom, tel, email, remarques?}  public : s'inscrire sur un poste de responsable où il reste de la place
//   POST api.php {action: lookup, q}  public : bénévoles dont l'e-mail ou le téléphone est exactement q (ids seulement)
//   POST api.php {action: login, password} / {action: logout}

require_once __DIR__ . '/library/auth.php';
require_once __DIR__ . '/library/tables.php';
require_once __DIR__ . '/library/html.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// créneau complet, ou poste de responsable pris entre-temps (message éventuel = celui renvoyé)
class FullException extends RuntimeException {}

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
    // texte riche (page contact, encadré d'appel) : balises et liens en liste blanche
    foreach (['contactHtml', 'contactHtmlBr', 'contactHtmlEn', 'appel', 'appelBr', 'appelEn'] as $k) {
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
    if (isset($d['ficheUrl']) && !preg_match('~^https?://\S+$~', $d['ficheUrl'])) fail('L’adresse de la fiche de poste doit commencer par https:// ou http://');
    if (isset($d['contactTel']) && !preg_match('/^\+?[0-9 .()-]{6,25}$/', $d['contactTel'])) fail('Numéro de téléphone invalide.');
}

function get_row(PDO $pdo, string $table, string $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    $st->closeCursor();
    return $r ? row_to_api($r) : null;
}

// Créneaux d'un responsable : creneauIds = liste complète, qui remplace les liens existants.
// Seuls les créneaux du même événement sont gardés. À appeler dans tx().
function set_resp_creneaux(PDO $pdo, string $rid, $ids): void
{
    if (!is_array($ids)) return;
    $pdo->prepare('DELETE FROM responsable_creneaux WHERE responsable_id = ?')->execute([$rid]);
    $ins = $pdo->prepare('INSERT INTO responsable_creneaux (id, event_id, responsable_id, creneau_id)
        SELECT ?, c.event_id, r.id, c.id FROM creneaux c JOIN responsables r ON r.event_id = c.event_id WHERE r.id = ? AND c.id = ?');
    foreach (array_unique(array_filter($ids, 'is_string')) as $cid) $ins->execute([new_id(), $rid, $cid]);
}

// Personnes d'un poste de responsable, en différentiel pour ne jamais effacer une inscription publique faite entre-temps :
// {remove: [ids], add: [{nom, tel?, email?} | {benevoleId}]}. À appeler dans tx().
function set_resp_personnes(PDO $pdo, string $rid, $p): void
{
    if (!is_array($p)) return;
    $del = $pdo->prepare('DELETE FROM responsable_personnes WHERE id = ? AND responsable_id = ?');
    foreach ((array)($p['remove'] ?? []) as $id) if (is_string($id)) $del->execute([$id, $rid]);
    $ins = $pdo->prepare('INSERT OR IGNORE INTO responsable_personnes (id, event_id, responsable_id, nom, tel, email, benevole_id)
        SELECT ?, event_id, id, ?, ?, ?, ? FROM responsables WHERE id = ?');
    foreach ((array)($p['add'] ?? []) as $a) {
        if (!is_array($a)) continue;
        $nom = trim(preg_replace('/\s+/u', ' ', (string)($a['nom'] ?? '')));
        $bid = trim((string)($a['benevoleId'] ?? ''));
        if ($nom === '' && $bid === '') continue;
        if (mb_strlen($nom) > 80 || preg_match('/[\p{C}<>]/u', $nom)) fail('Nom de responsable invalide (80 caractères max).');
        $tel = $bid === '' ? trim((string)($a['tel'] ?? '')) : ''; // bénévole : contact pris sur sa fiche
        $email = $bid === '' ? trim((string)($a['email'] ?? '')) : '';
        if ($tel !== '' && !preg_match('/^\+?[0-9 .()-]{6,25}$/', $tel)) fail('Numéro de téléphone invalide.');
        if ($email !== '' && (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) fail('Adresse e-mail invalide.');
        $ins->execute([new_id(), $nom === '' ? null : $nom, $tel ?: null, $email ?: null, $bid === '' ? null : $bid, $rid]);
    }
}

function clean_name($v): string
{
    $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
    if ($v === '' || mb_strlen($v) > 60 || preg_match('/[\p{C}<>]/u', $v)) fail('Indiquez un prénom et un nom valides (60 caractères max).');
    return $v;
}

// Remarques libres de l'inscription : on retire les balises HTML puis tout caractère hors liste blanche
// (lettres, chiffres, espaces, retours à la ligne, ponctuation courante, &). Plus de < > " ` { } $ \ etc.
// Le & est sans risque : les notes sont toujours échappées (esc()) à l'affichage.
function clean_remarques(string $s): string
{
    $s = strip_tags(str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $s));
    $s = preg_replace('/[^\p{L}\p{M}\p{N} \n.,;:!?\'’()\-\/%€+@«»…&]/u', '', $s) ?? '';
    $s = preg_replace(['/ {2,}/', '/\n{3,}/'], [' ', "\n\n"], $s);
    return trim($s);
}

// Identité saisie à l'inscription (créneau ou poste de responsable) : limite par session, puis prénom, nom,
// téléphone et e-mail obligatoires, remarques facultatives.
function signup_person(array $b): array
{
    if (!empty($b['website'])) out(['ok' => true]); // champ piège anti-robots

    $recent = array_filter($_SESSION['signups'] ?? [], fn($t) => $t > time() - 3600);
    if (count($recent) >= 20) fail('Trop d’inscriptions depuis cet appareil. Contactez l’organisation.', 429, 'rate');

    $prenom = clean_name($b['prenom'] ?? '');
    $nom = clean_name($b['nom'] ?? '');
    // Contact obligatoire à l'inscription, visible des seuls organisateurs.
    $tel = trim((string)($b['tel'] ?? '')) ?: null;
    $email = trim((string)($b['email'] ?? '')) ?: null;
    if ($tel === null || $email === null) fail('Le téléphone et l’e-mail sont obligatoires.');
    if (!preg_match('/^\+?[0-9 .()-]{6,25}$/', $tel)) fail('Numéro de téléphone invalide.');
    if ((mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) fail('Adresse e-mail invalide.');
    // Remarques ou suggestions (facultatif) : ajoutées aux notes de la fiche, visibles des seuls organisateurs.
    $rem = clean_remarques((string)($b['remarques'] ?? ''));
    if (mb_strlen($rem) > 1000) fail('Remarques trop longues (1000 caractères max).');
    return compact('prenom', 'nom', 'tel', 'email', 'rem');
}

// Fiche existante au même nom (sans tenir compte de la casse), sinon null.
function find_benevole(PDO $pdo, array $p): ?string
{
    $key = fn($s) => mb_strtolower($s, 'UTF-8');
    foreach ($pdo->query('SELECT id, prenom, nom FROM benevoles') as $r) {
        if ($key($r['prenom']) === $key($p['prenom']) && $key($r['nom']) === $key($p['nom'])) return $r['id'];
    }
    return null;
}

// Dans tx() : crée la fiche, ou complète seulement les champs vides d'une fiche existante (sans jamais écraser un contact).
function save_benevole(PDO $pdo, ?string $bid, array $p): string
{
    if (!$bid) {
        $bid = new_id();
        $pdo->prepare('INSERT INTO benevoles (id, prenom, nom, tel, email) VALUES (?, ?, ?, ?, ?)')->execute([$bid, $p['prenom'], $p['nom'], $p['tel'], $p['email']]);
    } else {
        $pdo->prepare('UPDATE benevoles SET tel = COALESCE(tel, ?), email = COALESCE(email, ?), updated_at = ? WHERE id = ?')
            ->execute([$p['tel'], $p['email'], now_iso(), $bid]);
    }
    return $bid;
}

// Dans tx() : remarque ajoutée aux notes, sans doublon (la même remarque renvoyée avec une 2e inscription n'est ajoutée qu'une fois).
function add_remarque(PDO $pdo, string $bid, string $rem): void
{
    if ($rem === '') return;
    $pdo->prepare("UPDATE benevoles SET notes = CASE WHEN notes IS NULL OR notes = '' THEN ? ELSE notes || ? || ? END, updated_at = ?
                    WHERE id = ? AND INSTR(COALESCE(notes, ''), ?) = 0")
        ->execute([$rem, "\n", $rem, now_iso(), $bid, $rem]);
}

function signup_done(): void
{
    $now = time();
    $_SESSION['signups'] = array_values(array_filter($_SESSION['signups'] ?? [], fn($t) => $t > $now - 3600));
    $_SESSION['signups'][] = $now;
}

// Inscription publique sur un créneau : prénom + nom, sans compte. Réutilise la fiche si le nom existe déjà.
function signup(PDO $pdo, array $b): void
{
    $p = signup_person($b);
    $st = $pdo->prepare('SELECT c.*, p.nom AS poste FROM creneaux c JOIN postes p ON p.id = c.poste_id WHERE c.id = ?');
    $st->execute([(string)($b['creneauId'] ?? '')]);
    $c = $st->fetch();
    $st->closeCursor(); // un curseur resté ouvert empêcherait tx() d'attendre le verrou (voir library/db.php)
    if (!$c) fail('Ce créneau n’existe plus.', 404, 'not_found');

    $bid = find_benevole($pdo, $p);
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

    // Fiche + affectation dans une seule transaction (verrou d'écriture pris dès le début, voir tx()).
    $aid = tx($pdo, function () use ($pdo, &$bid, $p, $c) {
        $bid = save_benevole($pdo, $bid, $p);
        // Insertion conditionnelle en une seule requête : impossible de dépasser le besoin, même à plusieurs en même temps.
        $st = $pdo->prepare("INSERT INTO affectations (id, event_id, creneau_id, benevole_id, statut)
            SELECT ?, ?, ?, ?, 'prevu'
             WHERE (SELECT COUNT(*) FROM affectations WHERE creneau_id = ? AND statut <> 'absent')
                 < (SELECT besoin FROM creneaux WHERE id = ?)");
        $aid = new_id();
        $st->execute([$aid, $c['event_id'], $c['id'], $bid, $c['id'], $c['id']]);
        if (!$st->rowCount()) throw new FullException();
        add_remarque($pdo, $bid, $p['rem']);
        return $aid;
    });

    signup_done();
    out(['ok' => true, 'benevoleId' => $bid, 'id' => $aid], 201);
}

// Inscription publique sur un poste de responsable : même identité que pour un créneau.
// L'insertion est conditionnelle : impossible de dépasser le besoin du poste, même à plusieurs en même temps.
function signup_resp(PDO $pdo, array $b): void
{
    $p = signup_person($b);
    $st = $pdo->prepare('SELECT * FROM responsables WHERE id = ?');
    $st->execute([(string)($b['responsableId'] ?? '')]);
    $r = $st->fetch();
    $st->closeCursor();
    if (!$r) fail('Ce poste de responsable n’existe plus.', 404, 'not_found');

    $bid = find_benevole($pdo, $p);
    if ($bid) {
        $st = $pdo->prepare('SELECT 1 FROM responsable_personnes WHERE responsable_id = ? AND benevole_id = ?');
        $st->execute([$r['id'], $bid]);
        $already = $st->fetchColumn();
        $st->closeCursor();
        if ($already) fail('Vous êtes déjà inscrit·e sur ce poste de responsable.', 409, 'conflict');
    }
    $pid = tx($pdo, function () use ($pdo, &$bid, $p, $r) {
        $bid = save_benevole($pdo, $bid, $p);
        $st = $pdo->prepare('INSERT INTO responsable_personnes (id, event_id, responsable_id, benevole_id)
            SELECT ?, ?, ?, ? WHERE (SELECT COUNT(*) FROM responsable_personnes WHERE responsable_id = ?)
                                  < (SELECT besoin FROM responsables WHERE id = ?)');
        $pid = new_id();
        $st->execute([$pid, $r['event_id'], $r['id'], $bid, $r['id'], $r['id']]);
        if (!$st->rowCount()) throw new FullException('Ce poste de responsable est déjà pourvu.');
        add_remarque($pdo, $bid, $p['rem']);
        return $pid;
    });

    signup_done();
    out(['ok' => true, 'benevoleId' => $bid, 'id' => $pid], 201);
}

// Recherche publique d'une inscription par e-mail ou téléphone : correspondance exacte seulement
// (pas de recherche partielle qui permettrait de deviner les contacts), et seuls les ids sont renvoyés.
function lookup(PDO $pdo, array $b): void
{
    $now = time();
    $recent = array_filter($_SESSION['lookups'] ?? [], fn($t) => $t > $now - 3600);
    if (count($recent) >= 30) fail('Trop de recherches depuis cet appareil. Réessayez plus tard.', 429, 'rate');
    $recent[] = $now;
    $_SESSION['lookups'] = array_values($recent);

    $q = trim((string)($b['q'] ?? ''));
    $digits = preg_replace('/\D/', '', $q);
    if (filter_var($q, FILTER_VALIDATE_EMAIL)) {
        $st = $pdo->prepare('SELECT id FROM benevoles WHERE LOWER(TRIM(email)) = ?');
        $st->execute([mb_strtolower($q, 'UTF-8')]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    } elseif (preg_match('/^\+?[0-9 .()-]+$/', $q) && strlen($digits) >= 9) {
        // 06…, +33 6… et 0033 6… : on compare les 9 derniers chiffres
        $key = substr($digits, -9);
        $ids = [];
        foreach ($pdo->query('SELECT id, tel FROM benevoles WHERE tel IS NOT NULL') as $r) {
            if (substr(preg_replace('/\D/', '', $r['tel']), -9) === $key) $ids[] = $r['id'];
        }
    } else {
        fail('Saisissez une adresse e-mail ou un numéro de téléphone complet.');
    }
    out(['ok' => true, 'ids' => $ids]);
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
        $extra = is_admin() && db_journal_mode() !== null ? ['dbMode' => db_journal_mode()] : [];
        out(['ok' => true, 'admin' => is_admin()] + $extra + fetch_all($pdo, is_admin()));
    }

    if ($method === 'POST' && $action === 'signup') signup($pdo, $body);
    if ($method === 'POST' && $action === 'signup_resp') signup_resp($pdo, $body);
    if ($method === 'POST' && $action === 'lookup') lookup($pdo, $body);

    if (!is_admin()) fail('Réservé aux organisateurs : connectez-vous.', 401, 'auth');
    if ($method !== 'POST') fail('Action inconnue.', 404, 'not_found');

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
            tx($pdo, function () use ($pdo, $sql, $newId, $d, $table, $data) {
                $pdo->prepare($sql)->execute(array_merge([$newId], array_values($d)));
                if ($table === 'responsables') {
                    set_resp_creneaux($pdo, $newId, $data['creneauIds'] ?? null);
                    set_resp_personnes($pdo, $newId, $data['personnes'] ?? null);
                }
            });
            out(['ok' => true, 'id' => $newId, 'row' => get_row($pdo, $table, $newId)], 201);

        case 'update':
            if (!$id) fail('Identifiant manquant.');
            $d = clean($table, $data);
            validate($table, $d, false);
            // responsables : on peut ne changer que ses personnes ou ses créneaux
            $links = $table === 'responsables' && (isset($data['personnes']) || isset($data['creneauIds']));
            if (!$d && !$links) fail('Rien à modifier.');
            $set = implode('', array_map(fn($k) => snake($k) . ' = ?, ', array_keys($d)));
            $found = tx($pdo, function () use ($pdo, $set, $d, $id, $table, $data) {
                $st = $pdo->prepare("UPDATE $table SET {$set}updated_at = ? WHERE id = ?");
                $st->execute(array_merge(array_values($d), [now_iso(), $id]));
                if (!$st->rowCount()) return false;
                if ($table === 'responsables') {
                    set_resp_creneaux($pdo, $id, $data['creneauIds'] ?? null);
                    set_resp_personnes($pdo, $id, $data['personnes'] ?? null);
                }
                return true;
            });
            if (!$found) fail('Élément introuvable (supprimé entre-temps ?).', 404, 'not_found');
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
} catch (FullException $e) {
    fail($e->getMessage() ?: 'Ce créneau est complet. Choisissez-en un autre.', 409, 'full');
} catch (PDOException $e) {
    $m = $e->getMessage();
    // Base occupée plus de busy_timeout (5 s) : rien n'a été écrit, l'interface réessaie une fois d'elle-même.
    if (is_busy($e)) { error_log('[regie] ' . $m); fail('Serveur occupé, réessayez dans un instant.', 503, 'busy'); }
    if (stripos($m, 'UNIQUE') !== false) fail('Ce bénévole est déjà sur ce créneau.', 409, 'conflict');
    if (stripos($m, 'FOREIGN KEY') !== false) fail('Élément lié introuvable (supprimé entre-temps ?).', 409, 'conflict');
    if (stripos($m, 'CHECK') !== false || stripos($m, 'NOT NULL') !== false) fail('Valeur refusée par la base.', 400);
    error_log('[regie] ' . $m);
    fail('Erreur de base de données.', 500, 'db');
}
