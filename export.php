<?php
// Exports : export.php?format=csv&event=<id>  (bénévoles × créneaux)
//           export.php?format=json            (sauvegarde complète, même forme que seed_kalanna2026.json)

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/tables.php';

if (!is_admin()) {
    http_response_code(401);
    exit('Réservé aux organisateurs : connectez-vous.');
}

function slug(string $s): string
{
    $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: 'export';
}

// Même règle que span() dans l'interface : avant 06:00 = nuit du jour indiqué.
function heures(string $debut, string $fin): string
{
    $m = fn($t) => (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);
    $d = ($m($fin) - $m($debut) + 1440) % 1440 ?: 1440;
    return str_replace('.', ',', (string)round($d / 60, 2));
}

$pdo = db();
$format = $_GET['format'] ?? 'csv';

if ($format === 'json') {
    $out = [];
    foreach (array_keys(TABLES) as $t) {
        $out[$t] = [];
        foreach (fetch_table($pdo, $t) as $row) {
            $id = $row['id'];
            unset($row['id']);
            $out[$t][$id] = array_filter($row, fn($v) => $v !== null);
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="regie-sauvegarde-' . gmdate('Y-m-d-His') . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$st = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$st->execute([$_GET['event'] ?? '']);
$event = $st->fetch();
if (!$event) {
    http_response_code(404);
    exit('Événement introuvable.');
}

$st = $pdo->prepare(
    "SELECT b.id AS bid, b.prenom, b.nom, b.tel, b.email, b.taille,
            c.jour, c.debut, c.fin, p.nom AS poste, a.statut
       FROM benevoles b
       LEFT JOIN affectations a ON a.benevole_id = b.id AND a.event_id = :ev
       LEFT JOIN creneaux c ON c.id = a.creneau_id
       LEFT JOIN postes p ON p.id = c.poste_id
      ORDER BY b.prenom, b.nom, b.id, c.jour,
               CASE WHEN c.debut < '06:00' THEN 1 ELSE 0 END, c.debut"
);
$st->execute([':ev' => $event['id']]);

$statuts = ['prevu' => 'Prévu', 'present' => 'Présent', 'absent' => 'Absent'];
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="benevoles-' . slug($event['nom']) . '.csv"');
$f = fopen('php://output', 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, ['Prénom', 'Nom', 'Téléphone', 'E-mail', 'T-shirt', 'Jour', 'Poste', 'Début', 'Fin', 'Heures', 'Statut'], ';', '"', '');
foreach ($st as $r) {
    fputcsv($f, [
        $r['prenom'], $r['nom'], $r['tel'], $r['email'], $r['taille'],
        $r['jour'], $r['poste'], $r['debut'], $r['fin'],
        $r['jour'] ? heures($r['debut'], $r['fin']) : '',
        $r['statut'] ? $statuts[$r['statut']] : '',
    ], ';', '"', '');
}
fclose($f);
