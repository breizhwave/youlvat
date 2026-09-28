<?php
// Liste blanche des tables/colonnes exposées par l'API, et conversion camelCase <-> snake_case.

const TABLES = [
    'events'       => ['cols' => ['nom', 'lieu', 'debut', 'fin', 'sheetUrl', 'imageUrl', 'contact', 'contactTel', 'contactInfos', 'contactHtml', 'contactHtmlBr', 'deroule'],
                       'req'  => ['nom', 'debut', 'fin'], 'int' => []],
    'postes'       => ['cols' => ['eventId', 'nom', 'couleur', 'responsable', 'description'],
                       'req'  => ['eventId', 'nom'], 'int' => ['couleur']],
    'creneaux'     => ['cols' => ['eventId', 'posteId', 'jour', 'debut', 'fin', 'besoin', 'note'],
                       'req'  => ['eventId', 'posteId', 'jour', 'debut', 'fin', 'besoin'], 'int' => ['besoin']],
    'benevoles'    => ['cols' => ['prenom', 'nom', 'tel', 'email', 'taille', 'competences', 'notes'],
                       'req'  => ['prenom', 'nom'], 'int' => []],
    'affectations' => ['cols' => ['eventId', 'creneauId', 'benevoleId', 'statut'],
                       'req'  => ['eventId', 'creneauId', 'benevoleId'], 'int' => []],
];

function snake(string $k): string
{
    return strtolower(preg_replace('/[A-Z]/', '_$0', $k));
}

function camel(string $k): string
{
    return lcfirst(str_replace('_', '', ucwords($k, '_')));
}

function row_to_api(array $row): array
{
    $out = [];
    foreach ($row as $k => $v) $out[camel($k)] = $v;
    if (isset($out['couleur'])) $out['couleur'] = (int)$out['couleur'];
    if (isset($out['besoin'])) $out['besoin'] = (int)$out['besoin'];
    return $out;
}

function fetch_table(PDO $pdo, string $table): array
{
    $rows = $pdo->query("SELECT * FROM $table ORDER BY created_at, id")->fetchAll();
    return array_map('row_to_api', $rows);
}

// Champs jamais montrés au public.
const PRIVATE_COLS = ['benevoles' => ['tel', 'email', 'notes']];

function fetch_all(PDO $pdo, bool $admin = true): array
{
    $out = [];
    foreach (array_keys(TABLES) as $t) {
        $rows = fetch_table($pdo, $t);
        if (!$admin && isset(PRIVATE_COLS[$t])) {
            $hide = array_flip(PRIVATE_COLS[$t]);
            $rows = array_map(fn($r) => array_diff_key($r, $hide), $rows);
        }
        $out[$t] = $rows;
    }
    return $out;
}

// Même règle que span() dans l'interface : un créneau qui commence avant 06:00 appartient
// à la nuit du jour indiqué ; fin < début = passe minuit. Renvoie [début, fin] en minutes absolues.
function creneau_span(array $c): array
{
    $m = fn($t) => (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);
    $d = $m($c['debut']);
    $s = intdiv(strtotime($c['jour'] . 'T00:00:00Z'), 60) + $d + ($d < 360 ? 1440 : 0);
    $len = ($m($c['fin']) - $d + 1440) % 1440 ?: 1440;
    return [$s, $s + $len];
}
