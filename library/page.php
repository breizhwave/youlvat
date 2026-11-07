<?php
// Rendu côté serveur commun aux trois pages publiques (index.php, inscription.php, contact.php) :
// événement courant, liste des événements, <title> / description / Open Graph, en-tête et pied de page.
// Le JavaScript reprend ensuite la main (traduction, rafraîchissement) ; ce HTML sert aux moteurs de recherche,
// aux aperçus de liens (WhatsApp, Facebook…) et à l'affichage avant le chargement des données.
require_once __DIR__ . '/db.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Événement de la page : ?event=, sinon déduit de ?creneau=, ?poste= ou ?responsable= (comme le JS). */
function page_event(): ?array {
    try {
        $pdo = db();
        $id = (string)($_GET['event'] ?? '');
        $find = function (string $sql, string $v) use ($pdo): string {
            $st = $pdo->prepare($sql); $st->execute([$v]); $r = $st->fetchColumn(); $st->closeCursor(); return (string)$r;
        };
        if ($id === '' && !empty($_GET['creneau']))     $id = $find('SELECT event_id FROM creneaux WHERE id = ?', (string)$_GET['creneau']);
        if ($id === '' && !empty($_GET['poste']))       $id = $find('SELECT event_id FROM postes WHERE id = ?', (string)$_GET['poste']);
        if ($id === '' && !empty($_GET['responsable'])) $id = $find('SELECT event_id FROM responsables WHERE id = ?', (string)$_GET['responsable']);
        if ($id === '') return null;
        $st = $pdo->prepare('SELECT id, nom, lieu, debut, fin, image_url FROM events WHERE id = ?');
        $st->execute([$id]); $e = $st->fetch(PDO::FETCH_ASSOC); $st->closeCursor();
        return $e ?: null;
    } catch (Throwable $e) { return null; }
}

/** Tous les événements, dans l'ordre du sélecteur : à venir (du plus proche) puis passés (du plus récent). */
function page_events(): array {
    try {
        $rows = db()->query('SELECT id, nom, debut, fin FROM events')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
    $today = gmdate('Y-m-d');
    $past = fn($e) => (($e['fin'] ?: $e['debut']) < $today) ? 1 : 0;
    usort($rows, fn($a, $b) => ($past($a) - $past($b)) ?: ($past($a) ? strcmp($b['debut'], $a['debut']) : strcmp($a['debut'], $b['debut'])));
    return $rows;
}

function ev_image(?array $e): string {
    $u = $e['image_url'] ?? '';
    return preg_match('#^https://#', $u) ? $u : '';
}

/** « Quimper · 29 décembre → 2 janvier 2027 », comme evWhen() côté JS (en français ; le JS traduit ensuite). */
function ev_when(?array $e): string {
    if (!$e) return '';
    static $mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $d = function (string $iso, bool $year) use ($mois) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) return $iso;
        return (int)$m[3] . ' ' . $mois[(int)$m[2] - 1] . ($year ? ' ' . $m[1] : '');
    };
    $date = '';
    if (!empty($e['debut'])) {
        $date = $d($e['debut'], false);
        if (!empty($e['fin']) && $e['fin'] !== $e['debut']) $date .= ' → ' . $d($e['fin'], true);
    }
    return implode(' · ', array_filter([$e['lieu'] ?? '', $date]));
}

/** <title>, description et balises Open Graph. $title = libellé de la page (sans le nom de l'événement). */
function page_meta(string $title, ?array $ev, string $desc): void {
    $app = config()['app_name'] ?? 'Régie Bénévoles';
    $full = $ev ? $ev['nom'] . ' · ' . $title : $title . ' · ' . $app;
    $img = ev_image($ev);
    echo '<title>' . h($full) . "</title>\n";
    echo '<meta name="description" content="' . h($desc) . "\">\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . h($app) . "\">\n";
    echo '<meta property="og:title" content="' . h($full) . "\">\n";
    echo '<meta property="og:description" content="' . h($desc) . "\">\n";
    if ($img) echo '<meta property="og:image" content="' . h($img) . "\">\n";
}
