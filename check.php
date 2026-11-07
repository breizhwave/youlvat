<?php
// TEMPORAIRE : diagnostic de mise en ligne. À SUPPRIMER du serveur (et du dossier) après usage.
// Affiche la taille et l'empreinte des fichiers PHP réellement présents, l'état du cache OPcache, puis le vide.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "Dossier : ", __DIR__, "\nPHP ", PHP_VERSION, "\n\n";
foreach (['library/tables.php', 'library/db.php', 'api.php', 'database/schema.sql'] as $f) {
    $p = __DIR__ . '/' . $f;
    echo str_pad($f, 22), is_file($p) ? filesize($p) . " o  md5 " . md5_file($p) . "  modifié " . gmdate('Y-m-d H:i', filemtime($p)) . " UTC" : 'ABSENT', "\n";
}
$t = @file_get_contents(__DIR__ . '/library/tables.php');
echo "\nlibrary/tables.php contient 'responsable_personnes' : ", $t !== false && strpos($t, 'responsable_personnes') !== false ? 'oui' : 'NON', "\n";
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    echo "OPcache : ", $s && !empty($s['opcache_enabled']) ? 'actif' : 'inactif', "\n";
    echo "OPcache vidé : ", function_exists('opcache_reset') && @opcache_reset() ? 'oui' : 'non (fonction désactivée par l’hébergeur)', "\n";
} else {
    echo "OPcache : non disponible\n";
}
