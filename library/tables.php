<?php
// Liste blanche des tables/colonnes exposées par l'API, et conversion camelCase <-> snake_case.

const TABLES = [
    'events'       => ['cols' => ['nom', 'lieu', 'debut', 'fin', 'sheetUrl', 'imageUrl', 'contact', 'contactTel', 'contactInfos', 'contactHtml', 'contactHtmlBr', 'contactHtmlEn', 'appel', 'appelBr', 'appelEn', 'deroule'],
                       'req'  => ['nom', 'debut', 'fin'], 'int' => []],
    'postes'       => ['cols' => ['eventId', 'nom', 'couleur', 'responsable', 'description'],
                       'req'  => ['eventId', 'nom'], 'int' => ['couleur']],
    'creneaux'     => ['cols' => ['eventId', 'posteId', 'jour', 'debut', 'fin', 'besoin', 'note'],
                       'req'  => ['eventId', 'posteId', 'jour', 'debut', 'fin', 'besoin'], 'int' => ['besoin']],
    'benevoles'    => ['cols' => ['prenom', 'nom', 'tel', 'email', 'taille', 'competences', 'notes'],
                       'req'  => ['prenom', 'nom'], 'int' => []],
    'affectations' => ['cols' => ['eventId', 'creneauId', 'benevoleId', 'statut'],
                       'req'  => ['eventId', 'creneauId', 'benevoleId'], 'int' => []],
    // postes de responsable : besoin = nombre de personnes voulues (inscription publique tant qu'il reste de la place)
    'responsables' => ['cols' => ['eventId', 'titre', 'besoin', 'description', 'ficheUrl'],
                       'req'  => ['eventId'], 'int' => ['besoin']],
    // personnes sur un poste de responsable : nom saisi ou bénévole ; écrites via data.personnes sur responsables, ou par signup_resp
    'responsable_personnes' => ['cols' => ['eventId', 'responsableId', 'nom', 'tel', 'email', 'benevoleId'],
                       'req'  => ['eventId', 'responsableId'], 'int' => []],
    // liens responsable <-> créneau : écrits via creneauIds sur responsables (voir set_resp_creneaux() dans api.php)
    'responsable_creneaux' => ['cols' => ['eventId', 'responsableId', 'creneauId'],
                       'req'  => ['eventId', 'responsableId', 'creneauId'], 'int' => []],
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
const PRIVATE_COLS = ['benevoles' => ['notes']];

// Nom de famille masqué pour le public : initiale + astérisques (« Dupont » → « D. ******* »).
// Même format que maskNom() dans inscription.php.
function mask_nom(?string $nom): string
{
    $nom = trim((string)$nom);
    return $nom === '' ? '' : mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') . '. *******';
}

// E-mail et téléphone masqués pour le public, juste de quoi distinguer deux homonymes :
// 2 premiers caractères de l'e-mail (« marie@x.fr » → « ma****@**** »), 2 premiers et 2 derniers chiffres du tél (« 06******78 », +33 6… compris).
function mask_email(?string $email): ?string
{
    $email = trim((string)$email);
    return $email === '' ? null : mb_substr($email, 0, 2, 'UTF-8') . '****@****';
}

function mask_tel(?string $tel): ?string
{
    $d = preg_replace('/\D/', '', (string)$tel);
    if (preg_match('/^(?:00)?33(\d{9})$/', $d, $m)) $d = '0' . $m[1]; // +33 6… / 0033 6… → 06…
    return strlen($d) < 4 ? null : substr($d, 0, 2) . '******' . substr($d, -2);
}

function fetch_all(PDO $pdo, bool $admin = true): array
{
    $out = [];
    foreach (array_keys(TABLES) as $t) {
        $rows = fetch_table($pdo, $t);
        if (!$admin && isset(PRIVATE_COLS[$t])) {
            $hide = array_flip(PRIVATE_COLS[$t]);
            $rows = array_map(fn($r) => array_diff_key($r, $hide), $rows);
            if ($t === 'benevoles') $rows = array_map(fn($r) => [
                'nom' => mask_nom($r['nom'] ?? ''),
                'tel' => mask_tel($r['tel'] ?? null),
                'email' => mask_email($r['email'] ?? null),
            ] + $r, $rows);
        }
        // contact d'un responsable saisi par nom : masqué comme celui d'un bénévole (le nom saisi reste public)
        if (!$admin && $t === 'responsable_personnes') $rows = array_map(fn($r) => [
            'tel' => mask_tel($r['tel'] ?? null),
            'email' => mask_email($r['email'] ?? null),
        ] + $r, $rows);
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
