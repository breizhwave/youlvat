<?php
/*
 * Suite de tests automatiques de la régie (sans Composer ni framework).
 *
 *   php test/run.php            tous les tests
 *   php test/run.php signup     seulement les groupes dont le nom contient « signup »
 *   php test/run.php --keep     garde le bac à sable à la fin (chemin affiché)
 *
 * Chaque lancement crée une copie jetable de l'appli dans le dossier temporaire du système, avec sa propre
 * config.php (mot de passe de test, base neuve chargée avec database/seed_demo.sql) et son propre serveur
 * `php -S` sur un port libre. La vraie base data/regie.sqlite et la vraie config.php ne sont jamais lues ni modifiées.
 * Prérequis : PHP ≥ 8.0 en ligne de commande avec pdo_sqlite, curl, dom, mbstring.
 * Code de sortie : 0 si tout passe, 1 sinon (utilisable avant un dépôt FTP ou en intégration continue).
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // jamais depuis le web (si test/ est déposé par erreur)
error_reporting(E_ALL);
ini_set('display_errors', '1');

const ROOT = __DIR__ . '/..';
const TEST_PASSWORD = 'test-pass';

$args = array_slice($argv, 1);
$keep = in_array('--keep', $args, true);
$filter = array_values(array_filter($args, fn($a) => $a[0] !== '-'))[0] ?? '';

foreach (['pdo_sqlite', 'curl', 'dom', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) { fwrite(STDERR, "Extension PHP manquante : $ext\n"); exit(2); }
}

/* ---------------------------------------------------------------- mini-framework */

$T = ['pass' => 0, 'fail' => 0, 'failures' => [], 'group' => ''];

function group(string $name, callable $fn): void
{
    global $T, $filter;
    if ($filter !== '' && stripos($name, $filter) === false) return;
    $T['group'] = $name;
    echo "\n\033[1m$name\033[0m\n";
    try { $fn(); }
    catch (Throwable $e) { fail_test('exception : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'); }
}

function ok(bool $cond, string $label, $detail = null): bool
{
    global $T;
    if ($cond) { $T['pass']++; echo "  \033[32m✓\033[0m $label\n"; return true; }
    fail_test($label . ($detail !== null ? ' — ' . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE)) : ''));
    return false;
}

function eq($actual, $expected, string $label): bool
{
    return ok($actual === $expected, $label, $actual === $expected ? null : 'attendu ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', obtenu ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
}

function fail_test(string $msg): void
{
    global $T;
    $T['fail']++;
    $T['failures'][] = "[{$T['group']}] $msg";
    echo "  \033[31m✗ $msg\033[0m\n";
}

function contains(string $hay, string $needle, string $label): bool
{
    return ok(str_contains($hay, $needle), $label, str_contains($hay, $needle) ? null : "« $needle » absent");
}

function lacks(string $hay, string $needle, string $label): bool
{
    return ok(!str_contains($hay, $needle), $label, !str_contains($hay, $needle) ? null : "« $needle » présent");
}

/* ---------------------------------------------------------------- bac à sable + serveur */

function copy_tree(string $src, string $dst, array $skip): void
{
    @mkdir($dst, 0775, true);
    foreach (new DirectoryIterator($src) as $f) {
        if ($f->isDot()) continue;
        $rel = $f->getFilename();
        if (in_array($rel, $skip, true)) continue;
        if ($f->isDir()) copy_tree($f->getPathname(), "$dst/$rel", []);
        else copy($f->getPathname(), "$dst/$rel");
    }
}

function rm_tree(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($dir);
}

function free_port(): int
{
    $s = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    return $port;
}

$SANDBOX = sys_get_temp_dir() . '/regie-tests-' . getmypid();
rm_tree($SANDBOX);
copy_tree(realpath(ROOT), $SANDBOX, ['.git', 'test', 'config.php', 'lib', 'reference', 'doc']);
foreach (glob("$SANDBOX/data/regie.sqlite*") ?: [] as $f) unlink($f);
@unlink("$SANDBOX/data/.journal_warned");
$hash = var_export(password_hash(TEST_PASSWORD, PASSWORD_DEFAULT), true);
file_put_contents("$SANDBOX/config.php", <<<PHP
<?php
// config de test (générée par test/run.php)
return [
    'app_name'            => 'Régie Test',
    'admin_password_hash' => $hash,
    'dsn'     => 'sqlite:' . __DIR__ . '/data/regie.sqlite',
    'db_user' => null,
    'db_pass' => null,
    'seed_on_create' => true,
    'seed_file'      => 'seed_demo.sql',
];
PHP);

$PORT = free_port();
$BASE = "http://127.0.0.1:$PORT/";
$serverLog = "$SANDBOX/server.log";
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$PORT", '-t', $SANDBOX], [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes);
register_shutdown_function(function () use (&$proc, $SANDBOX, &$keep) {
    if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
    if ($keep) echo "\nBac à sable conservé : $SANDBOX\n"; else rm_tree($SANDBOX);
});
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $PORT, $en, $es, 0.1)) break; usleep(100000); }

echo "Bac à sable : $SANDBOX\nServeur     : $BASE\n";

/* ---------------------------------------------------------------- client HTTP (une session par client) */

final class Client
{
    public string $csrf = '';
    private string $jar;
    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'regie-jar');
    }
    public function __destruct() { @unlink($this->jar); }

    public function handle(string $method, string $path, ?array $body = null, bool $csrf = true)
    {
        $h = curl_init($this->base . $path);
        $headers = [];
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20]);
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            if ($csrf) $headers[] = 'X-CSRF-Token: ' . $this->csrf;
            curl_setopt($h, CURLOPT_POST, true);
            curl_setopt($h, CURLOPT_POSTFIELDS, json_encode($body ?? [], JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($h, CURLOPT_HTTPHEADER, $headers);
        return $h;
    }

    public static function result($h, ?string $raw = null): array
    {
        $raw ??= (string)curl_multi_getcontent($h);
        $hs = curl_getinfo($h, CURLINFO_HEADER_SIZE);
        $code = curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        $head = substr($raw, 0, $hs);
        $body = substr($raw, $hs);
        return ['code' => $code, 'headers' => $head, 'body' => $body, 'json' => json_decode($body, true)];
    }

    public function req(string $method, string $path, ?array $body = null, bool $csrf = true): array
    {
        $h = $this->handle($method, $path, $body, $csrf);
        $raw = (string)curl_exec($h);
        return self::result($h, $raw);
    }

    /** Ouvre une page, récupère le jeton CSRF de la session (window.REGIE). */
    public function open(string $path = 'index.php'): array
    {
        $r = $this->req('GET', $path);
        if (preg_match('/"csrf":"([0-9a-f]+)"/', $r['body'], $m)) $this->csrf = $m[1];
        return $r;
    }
    public function api(array $body, bool $csrf = true): array { return $this->req('POST', 'api.php', $body, $csrf); }
    public function all(): array { return $this->req('GET', 'api.php?action=all')['json'] ?? []; }
    public function login(string $pw = TEST_PASSWORD): array { if (!$this->csrf) $this->open(); return $this->api(['action' => 'login', 'password' => $pw]); }
}

function client(): Client { global $BASE; $c = new Client($BASE); $c->open(); return $c; }

function admin(): Client
{
    static $a = null;
    if (!$a) { $a = client(); $a->login(); }
    return $a;
}

/** Bénévole inscrit sur un créneau (données valides par défaut). */
function signup(Client $c, string $creneauId, array $over = []): array
{
    return $c->api($over + ['action' => 'signup', 'creneauId' => $creneauId, 'prenom' => 'Test', 'nom' => 'Person', 'tel' => '06 11 22 33 44', 'email' => 'test@example.org']);
}

function by_id(array $rows, string $id): ?array { foreach ($rows as $r) if (($r['id'] ?? null) === $id) return $r; return null; }
function find_ben(array $all, string $prenom): ?array { foreach ($all['benevoles'] ?? [] as $b) if ($b['prenom'] === $prenom) return $b; return null; }
function count_affs(array $all, string $cid): int { return count(array_filter($all['affectations'] ?? [], fn($a) => $a['creneauId'] === $cid && $a['statut'] !== 'absent')); }

/** Crée une ligne en admin et renvoie son id. */
function create(string $table, array $data): string
{
    $r = admin()->api(['action' => 'create', 'table' => $table, 'data' => $data]);
    if ($r['code'] !== 201) throw new RuntimeException("création $table refusée : " . $r['body']);
    return $r['json']['id'];
}

/* ================================================================ 1. tests unitaires (sans serveur) */

require_once ROOT . '/library/tables.php';
require_once ROOT . '/library/html.php';
require_once ROOT . '/library/page.php';

group('unit: masquage des données privées', function () {
    eq(mask_nom('Dupont'), 'D. *******', 'nom → initiale');
    eq(mask_nom('élodie'), 'É. *******', 'initiale accentuée en majuscule');
    eq(mask_nom(''), '', 'nom vide');
    eq(mask_email('marie@example.org'), 'ma****@****', 'e-mail → 2 premiers caractères');
    eq(mask_email(''), null, 'e-mail vide → null');
    eq(mask_tel('06 12 34 56 78'), '06******78', 'tél → 2 premiers et 2 derniers chiffres');
    eq(mask_tel('+33 6 12 34 56 78'), '06******78', '+33 ramené à 0');
    eq(mask_tel('0033612345678'), '06******78', '0033 ramené à 0');
    eq(mask_tel('12'), null, 'tél trop court → null');
});

group('unit: règle de nuit et passage de minuit (creneau_span)', function () {
    $c = fn($j, $d, $f) => creneau_span(['jour' => $j, 'debut' => $d, 'fin' => $f]);
    $eve = $c('2026-12-31', '21:00', '00:00');
    $night = $c('2026-12-31', '00:00', '02:00');
    eq($eve[1] - $eve[0], 180, '21:00–00:00 dure 3 h (passe minuit)');
    eq($night[0], $eve[1], '00:00 le 31 = nuit du 31 au 1er, juste après 21:00–00:00 le 31');
    $late = $c('2026-12-31', '22:00', '02:00');
    ok($late[0] < $night[1] && $night[0] < $late[1], '22:00–02:00 chevauche 00:00–02:00 du même jour');
    $morning = $c('2026-12-31', '06:00', '08:00');
    ok($morning[0] < $eve[0], '06:00 appartient bien à la journée (pas à la nuit)');
    $full = $c('2026-12-31', '10:00', '10:00');
    eq($full[1] - $full[0], 1440, 'début = fin → 24 h');
});

group('unit: nettoyage du texte riche (sanitize_html)', function () {
    eq(sanitize_html('<p>Bonjour <b>tout</b> le monde</p>'), '<p>Bonjour <b>tout</b> le monde</p>', 'balises autorisées gardées');
    lacks(sanitize_html('<p>a<script>alert(1)</script></p>'), 'script', 'script supprimé avec son contenu');
    lacks(sanitize_html('<p onclick="x()" style="color:red">a</p>'), 'onclick', 'attributs retirés');
    $a = sanitize_html('<a href="javascript:alert(1)">x</a>');
    lacks($a, 'javascript', 'lien javascript: neutralisé');
    $a = sanitize_html('<a href="https://kalanna.bzh" onmouseover="x">site</a>');
    contains($a, 'href="https://kalanna.bzh"', 'lien https gardé');
    contains($a, 'rel="noopener"', 'rel=noopener ajouté');
    lacks($a, 'onmouseover', 'gestionnaire d\'événement retiré');
    eq(sanitize_html('<div>bloc</div>'), '<p>bloc</p>', 'div → p');
    eq(sanitize_html('<span>texte</span>'), 'texte', 'span retiré, texte gardé');
    eq(sanitize_html('<p><br></p>'), '', 'éditeur vide → chaîne vide');
    lacks(sanitize_html('<p>a<!-- commentaire --></p>'), 'commentaire', 'commentaires retirés');
    lacks(sanitize_html('<img src=x onerror=alert(1)>'), 'img', 'img refusée');
});

group('unit: conversions et dates', function () {
    eq(snake('eventId'), 'event_id', 'snake()');
    eq(camel('contact_html_br'), 'contactHtmlBr', 'camel()');
    eq(ev_when(['lieu' => 'Quimper', 'debut' => '2026-12-29', 'fin' => '2027-01-02']), 'Quimper · 29 décembre → 2 janvier 2027', 'ev_when() sur deux années');
    eq(ev_when(['lieu' => '', 'debut' => '2027-07-09', 'fin' => '2027-07-09']), '9 juillet', 'ev_when() un seul jour, sans lieu');
    eq(ev_when(null), '', 'ev_when(null)');
    eq(ev_image(['image_url' => 'http://x.fr/a.png']), '', 'image en http refusée');
    eq(ev_image(['image_url' => 'https://x.fr/a.png']), 'https://x.fr/a.png', 'image en https acceptée');
});

/* ================================================================ 2. pages (rendu serveur) */

group('pages: rendu, en-tête, pied de page', function () {
    $c = client();
    foreach (['index.php', 'inscription.php', 'contact.php', 'aide.php'] as $p) {
        $r = $c->req('GET', "$p?event=demo");
        eq($r['code'], 200, "$p répond 200");
        contains($r['body'], '<header class="band">', "$p : en-tête commun");
        contains($r['body'], 'class="site-foot"', "$p : pied de page commun");
        contains($r['body'], 'href="https://wave.bzh/fr/breizhvibe/youlvat/"', "$p : lien Breizhvibe");
        contains($r['body'], '<title>Summer Street Festival (demo) · ', "$p : <title> avec l'événement");
        contains($r['body'], '<meta name="description" content="', "$p : meta description");
        contains($r['body'], 'og:title', "$p : Open Graph");
        contains($r['body'], 'Town square · 9 juillet → 11 juillet 2027', "$p : lieu et dates écrits par le serveur");
        contains($r['body'], '<option value="demo" selected>', "$p : événement sélectionné dans la liste");
        ok(!preg_match('/(Warning|Notice|Fatal error|Deprecated):/', $r['body']), "$p : aucune erreur PHP dans la page");
    }
    $r = $c->req('GET', 'index.php');
    contains($r['body'], 'data-i18n="Choisissez un événement"', 'index.php sans événement : « Choisissez un événement »');
    contains($r['body'], '<div class="wrap tabrow" hidden>', 'index.php sans événement : onglets cachés');
    $r = $c->req('GET', 'index.php?event=inconnu');
    contains($r['body'], 'Choisissez un événement', 'événement inconnu traité comme absent');
    $r = $c->req('GET', 'index.php?creneau=demo-c08');
    contains($r['body'], '<title>Summer Street Festival (demo)', 'événement déduit de ?creneau=');
    $r = $c->req('GET', 'index.php?poste=bar');
    contains($r['body'], '<title>Summer Street Festival (demo)', 'événement déduit de ?poste=');
    $r = $c->req('GET', 'inscription.php?event=demo');
    contains($r['body'], 'href="index.php?event=demo&amp;tab=resps"', 'inscription.php : liens des onglets avec ?event=');
    contains($r['body'], 'data-nav="signup" href="inscription.php?event=demo" aria-current="page"', 'inscription.php : onglet courant');
    $r = $c->req('GET', 'contact.php?event=demo');
    contains($r['body'], 'data-nav="contact" href="contact.php?event=demo" aria-current="page"', 'contact.php : onglet courant');
    // page d'aide : trois langues dans le HTML, une seule affichée ; lien « Aide » sur toutes les pages
    $r = $c->req('GET', 'aide.php?event=demo');
    contains($r['body'], 'data-nav="aide" href="aide.php?event=demo" aria-current="page"', 'aide.php : onglet courant');
    foreach (['fr' => '<h1>Aide</h1>', 'br' => '<h1>Sikour</h1>', 'en' => '<h1>Help</h1>'] as $l => $h1) contains($r['body'], $h1, "aide.php : version $l présente");
    contains($r['body'], 'data-help="fr">', 'aide.php : français affiché par défaut');
    contains($r['body'], 'data-help="br" hidden>', 'aide.php : breton caché par défaut');
    contains($c->req('GET', 'aide.php?lang=en')['body'], 'data-help="en">', 'aide.php?lang=en : anglais affiché sans JavaScript');
    eq(substr_count($r['body'], 'id="historique"'), 3, 'aide.php : section historique dans les trois langues');
    contains($c->req('GET', 'aide.php')['body'], '<h1>Aide</h1>', 'aide.php sans événement : s\'affiche');
    contains($c->req('GET', 'index.php?event=demo')['body'], 'id="helpTab" href="aide.php?event=demo"', 'index.php : onglet Aide');
    contains($c->req('GET', 'contact.php?event=demo')['body'], 'data-nav="aide" href="aide.php?event=demo"', 'contact.php : onglet Aide');
    foreach (['assets/app.css', 'assets/i18n.js', 'assets/combo.js', 'assets/nav.js'] as $f) {
        ok((bool)preg_match('~' . preg_quote($f, '~') . '\?v=\d+~', $c->req('GET', 'index.php?event=demo')['body']), "$f appelé avec ?v= (cache Cloudflare)");
    }
});

group('pages: image, échappement HTML', function () {
    $a = admin();
    $a->api(['action' => 'update', 'table' => 'events', 'id' => 'demo', 'data' => ['imageUrl' => 'https://example.org/logo.png']]);
    $r = client()->req('GET', 'index.php?event=demo');
    contains($r['body'], '<meta property="og:image" content="https://example.org/logo.png">', 'og:image');
    contains($r['body'], 'id="evImg" class="evimg" alt="Summer Street Festival (demo)" src="https://example.org/logo.png"', 'image dans l\'en-tête');
    $id = create('events', ['nom' => 'Fête <script>alert(1)</script> & "co"', 'debut' => '2027-08-01', 'fin' => '2027-08-02']);
    $r = client()->req('GET', "index.php?event=$id");
    lacks($r['body'], '<script>alert(1)</script>', 'nom d\'événement échappé (pas de script injecté)');
    contains($r['body'], 'Fête &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;co&quot;', 'nom affiché échappé');
    $a->api(['action' => 'delete', 'table' => 'events', 'id' => $id]);
});

/* ================================================================ 3. API publique, connexion, CSRF */

group('api: lecture publique et protections', function () {
    $c = client();
    $all = $c->all();
    eq($all['ok'] ?? null, true, 'action=all répond');
    eq($all['admin'] ?? null, false, 'public : admin = false');
    eq(count($all['creneaux'] ?? []), 13, '13 créneaux de démo');
    ok(!isset($all['dbMode']), 'public : mode de la base non exposé');
    eq($c->api(['action' => 'signup'], false)['code'], 403, 'POST sans jeton CSRF → 403');
    $bad = clone $c; $bad->csrf = str_repeat('0', 64);
    eq($bad->api(['action' => 'lookup', 'q' => 'a@b.fr'])['json']['error'] ?? null, 'csrf', 'mauvais jeton CSRF → csrf');
    $r = $c->api(['action' => 'create', 'table' => 'postes', 'data' => ['eventId' => 'demo', 'nom' => 'Pirate']]);
    eq($r['code'], 401, 'écriture sans connexion → 401');
    eq($c->req('GET', 'export.php?format=json')['code'], 401, 'export.php sans connexion → 401');
    $r = $c->req('POST', 'api.php', null); // corps vide + jeton
    ok(in_array($r['code'], [400, 401, 404], true), 'POST vide refusé', $r['code']);
    eq(admin()->api(['action' => 'create', 'table' => 'sqlite_master', 'data' => []])['json']['message'] ?? null, 'Table non autorisée.', 'table hors liste blanche refusée');
});

group('api: connexion organisateurs', function () {
    $c = client();
    $r = $c->login('mauvais');
    eq($r['code'], 401, 'mauvais mot de passe → 401');
    $r = $c->login();
    eq($r['json']['ok'] ?? null, true, 'bon mot de passe → ok');
    $c->open(); // nouveau jeton après session_regenerate_id
    $all = $c->all();
    eq($all['admin'] ?? null, true, 'connecté : admin = true');
    eq($all['dbMode'] ?? null, 'wal', 'mode WAL obtenu');
    $r = $c->req('GET', 'index.php?event=demo');
    lacks($r['body'], '<body class="ro">', 'connecté : page en mode édition');
    $c->api(['action' => 'logout']);
    eq($c->all()['admin'] ?? null, false, 'déconnexion');
});

/* ================================================================ 4. inscription publique */

group('signup: validations', function () {
    $c = client();
    $r = signup($c, 'demo-c01', ['tel' => '']);
    eq([$r['code'], $r['json']['message'] ?? ''], [400, 'Le téléphone et l’e-mail sont obligatoires.'], 'téléphone obligatoire');
    $r = signup($c, 'demo-c01', ['email' => '']);
    eq($r['code'], 400, 'e-mail obligatoire');
    eq(signup($c, 'demo-c01', ['email' => 'pas-un-email'])['code'], 400, 'e-mail invalide refusé');
    eq(signup($c, 'demo-c01', ['tel' => 'abc'])['code'], 400, 'téléphone invalide refusé');
    eq(signup($c, 'demo-c01', ['prenom' => '<b>x</b>'])['code'], 400, 'prénom avec < > refusé');
    eq(signup($c, 'demo-c01', ['nom' => str_repeat('a', 61)])['code'], 400, 'nom de plus de 60 caractères refusé');
    eq(signup($c, 'inexistant')['code'], 404, 'créneau inexistant → 404');
    $before = count(admin()->all()['benevoles']);
    $r = signup($c, 'demo-c01', ['website' => 'http://spam', 'prenom' => 'Robot']);
    eq($r['json']['ok'] ?? null, true, 'champ piège : réponse « ok » trompeuse');
    eq(count(admin()->all()['benevoles']), $before, 'champ piège : rien n\'est écrit');
});

group('signup: inscription, masquage, réutilisation de la fiche', function () {
    $c = client();
    $r = signup($c, 'demo-c01', ['prenom' => 'Marie', 'nom' => 'Dupont', 'tel' => '06 12 34 56 78', 'email' => 'marie@example.org', 'remarques' => "<b>Je viens</b> avec {ma} \$voiture\n\n\n\nmerci & bravo"]);
    eq($r['code'], 201, 'inscription → 201');
    $bid = $r['json']['benevoleId'] ?? '';
    $pub = find_ben(client()->all(), 'Marie');
    eq($pub['nom'] ?? null, 'D. *******', 'public : nom masqué');
    eq($pub['tel'] ?? null, '06******78', 'public : téléphone masqué');
    eq($pub['email'] ?? null, 'ma****@****', 'public : e-mail masqué');
    ok(!array_key_exists('notes', $pub ?? []), 'public : notes retirées');
    $adm = find_ben(admin()->all(), 'Marie');
    eq([$adm['nom'], $adm['tel'], $adm['email']], ['Dupont', '06 12 34 56 78', 'marie@example.org'], 'organisateurs : tout est visible');
    eq($adm['notes'], "Je viens avec ma voiture\n\nmerci & bravo", 'remarques nettoyées (balises, { } $ retirés, & gardé)');
    eq(signup($c, 'demo-c01', ['prenom' => 'Marie', 'nom' => 'Dupont'])['json']['error'] ?? null, 'conflict', 'déjà inscrite sur ce créneau → conflict');
    $r = signup($c, 'demo-c03', ['prenom' => 'MARIE', 'nom' => 'dupont', 'tel' => '07 00 00 00 00', 'email' => 'autre@example.org', 'remarques' => '<b>Je viens</b> avec {ma} $voiture merci & bravo']);
    eq($r['json']['benevoleId'] ?? null, $bid, 'même nom (casse différente) → même fiche');
    $adm = find_ben(admin()->all(), 'Marie');
    eq([$adm['tel'], $adm['email']], ['06 12 34 56 78', 'marie@example.org'], 'contact existant jamais écrasé');
    $adm2 = find_ben(admin()->all(), 'Marie');
    eq(substr_count($adm2['notes'], 'Je viens'), 2, 'remarque différente ajoutée');
    signup($c, 'demo-c13', ['prenom' => 'Marie', 'nom' => 'Dupont', 'remarques' => 'Je viens avec ma voiture']);
    eq(substr_count(find_ben(admin()->all(), 'Marie')['notes'], 'Je viens avec ma voiture'), 2, 'remarque déjà présente pas recopiée');
    $r = signup($c, 'demo-c09', ['prenom' => 'Lena', 'nom' => 'Kerg', 'tel' => '0600000001', 'email' => 'lena@example.org']);
    $r2 = signup($c, 'demo-c06', ['prenom' => 'Lena', 'nom' => 'Kerg', 'tel' => '0600000001', 'email' => 'lena@example.org']);
    eq([$r2['code'], $r2['json']['error'] ?? null, $r2['json']['poste'] ?? null], [409, 'overlap', 'Food stand'], 'même heure ailleurs → overlap (avec le poste)');
});

group('signup: règle de nuit', function () {
    $c = client();
    $p = ['prenom' => 'Nuit', 'nom' => 'Blanche', 'tel' => '0600000002', 'email' => 'nuit@example.org'];
    eq(signup($c, 'demo-c07', $p)['code'], 201, 'bar 21:00–00:00');
    eq(signup($c, 'demo-c08', $p)['code'], 201, 'bar 00:00–02:00 (nuit du 10 au 11) : pas de chevauchement');
    eq(signup($c, 'demo-c12', $p)['json']['error'] ?? null, 'overlap', 'vestiaire 22:00–02:00 chevauche les deux');
});

group('signup: créneau complet, absents, accès simultanés', function () {
    $p = fn($n) => ['prenom' => "Solo$n", 'nom' => 'Test', 'tel' => '060000001' . $n, 'email' => "solo$n@example.org"];
    eq(signup(client(), 'demo-c11', $p(1))['code'], 201, 'dernière place prise');
    $r = signup(client(), 'demo-c11', $p(2));
    eq([$r['code'], $r['json']['error'] ?? null], [409, 'full'], 'créneau complet → full');
    // un absent libère la place
    $aff = array_values(array_filter(admin()->all()['affectations'], fn($a) => $a['creneauId'] === 'demo-c11'))[0];
    admin()->api(['action' => 'update', 'table' => 'affectations', 'id' => $aff['id'], 'data' => ['statut' => 'absent']]);
    eq(signup(client(), 'demo-c11', $p(2))['code'], 201, 'absent : la place se libère');
    // 6 inscriptions en même temps sur un créneau de 2 places
    $cid = create('creneaux', ['eventId' => 'demo', 'posteId' => 'kids', 'jour' => '2027-07-11', 'debut' => '08:00', 'fin' => '09:00', 'besoin' => 2]);
    $mh = curl_multi_init(); $hs = []; $clients = [];
    for ($i = 0; $i < 6; $i++) {
        $clients[$i] = client();
        $h = $clients[$i]->handle('POST', 'api.php', ['action' => 'signup', 'creneauId' => $cid, 'prenom' => "Rush$i", 'nom' => 'Test', 'tel' => '06000002' . $i . '0', 'email' => "rush$i@example.org"]);
        curl_multi_add_handle($mh, $h); $hs[] = $h;
    }
    do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1.0); } while ($running && $st === CURLM_OK);
    $codes = array_map(fn($h) => Client::result($h)['code'], $hs);
    sort($codes);
    eq($codes, [201, 201, 409, 409, 409, 409], '6 inscriptions simultanées sur 2 places : exactement 2 acceptées');
    eq(count_affs(admin()->all(), $cid), 2, 'besoin jamais dépassé en base');
    foreach ($hs as $h) curl_multi_remove_handle($mh, $h);
});

group('signup: limite de 20 inscriptions par heure et par session', function () {
    $cid = create('creneaux', ['eventId' => 'demo', 'posteId' => 'setup', 'jour' => '2027-07-11', 'debut' => '15:00', 'fin' => '16:00', 'besoin' => 40]);
    $c = client(); $codes = [];
    for ($i = 1; $i <= 21; $i++) $codes[] = signup($c, $cid, ['prenom' => "Rate$i", 'nom' => 'Test', 'email' => "rate$i@example.org"])['code'];
    eq(count(array_filter($codes, fn($x) => $x === 201)), 20, '20 inscriptions acceptées');
    eq(end($codes), 429, 'la 21e → 429');
    eq(signup(client(), $cid, ['prenom' => 'Autre', 'nom' => 'Session'])['code'], 201, 'une autre session n\'est pas bloquée');
});

/* ================================================================ 5. recherche par e-mail / téléphone */

group('lookup: correspondance exacte seulement', function () {
    $c = client();
    $bid = signup($c, 'demo-c02', ['prenom' => 'Look', 'nom' => 'Up', 'tel' => '06 98 76 54 32', 'email' => 'Look.Up@Example.org'])['json']['benevoleId'];
    eq(client()->api(['action' => 'lookup', 'q' => 'look.up@example.org'])['json']['ids'] ?? null, [$bid], 'e-mail exact (casse ignorée)');
    eq(client()->api(['action' => 'lookup', 'q' => '+33 6 98 76 54 32'])['json']['ids'] ?? null, [$bid], 'téléphone au format +33');
    eq(client()->api(['action' => 'lookup', 'q' => '0698765432'])['json']['ids'] ?? null, [$bid], 'téléphone sans espaces');
    eq(client()->api(['action' => 'lookup', 'q' => 'look'])['code'], 400, 'recherche partielle refusée');
    eq(client()->api(['action' => 'lookup', 'q' => '0698'])['code'], 400, 'numéro incomplet refusé');
    eq(client()->api(['action' => 'lookup', 'q' => 'inconnu@example.org'])['json']['ids'] ?? null, [], 'inconnu → liste vide');
    $r = client()->api(['action' => 'lookup', 'q' => 'look.up@example.org']);
    eq(array_keys($r['json']), ['ok', 'ids'], 'seuls des ids sont renvoyés');
    $c = client(); $last = null;
    for ($i = 0; $i < 31; $i++) $last = $c->api(['action' => 'lookup', 'q' => 'x@example.org'])['code'];
    eq($last, 429, '31e recherche dans l\'heure → 429');
});

/* ================================================================ 6. responsables */

group('responsables: création, inscription publique, masquage', function () {
    $a = admin();
    $r = $a->api(['action' => 'create', 'table' => 'responsables', 'data' => [
        'eventId' => 'demo', 'titre' => 'Chef de bar', 'besoin' => 2, 'ficheUrl' => 'https://example.org/fiche',
        'creneauIds' => ['demo-c06', 'demo-c07', 'inexistant'],
        'personnes' => ['add' => [['nom' => 'Yann Saisi', 'tel' => '06 55 44 33 22', 'email' => 'yann@example.org']]],
    ]]);
    eq($r['code'], 201, 'poste de responsable créé avec créneaux et une personne');
    $rid = $r['json']['id'];
    $all = $a->all();
    $links = array_values(array_filter($all['responsable_creneaux'], fn($l) => $l['responsableId'] === $rid));
    eq(count($links), 2, 'créneau inexistant ignoré, 2 liens');
    $pub = array_values(array_filter(client()->all()['responsable_personnes'], fn($p) => $p['responsableId'] === $rid))[0];
    eq([$pub['nom'], $pub['tel'], $pub['email']], ['Yann Saisi', '06******22', 'ya****@****'], 'public : nom saisi visible, contacts masqués');
    $c = client();
    $s = $c->api(['action' => 'signup_resp', 'responsableId' => $rid, 'prenom' => 'Resp', 'nom' => 'Public', 'tel' => '0611111111', 'email' => 'resp@example.org']);
    eq($s['code'], 201, 'inscription publique sur la place restante');
    eq($c->api(['action' => 'signup_resp', 'responsableId' => $rid, 'prenom' => 'Resp', 'nom' => 'Public', 'tel' => '0611111111', 'email' => 'resp@example.org'])['json']['error'] ?? null, 'conflict', 'déjà sur le poste → conflict');
    $f = client()->api(['action' => 'signup_resp', 'responsableId' => $rid, 'prenom' => 'Trop', 'nom' => 'Tard', 'tel' => '0622222222', 'email' => 'tard@example.org']);
    eq([$f['code'], $f['json']['error'] ?? null], [409, 'full'], 'poste pourvu → full');
    eq(count(array_filter($a->all()['affectations'], fn($x) => $x['benevoleId'] === $s['json']['benevoleId'])), 0, 'aucune affectation créée pour le responsable');
    // modification en différentiel : retirer la personne saisie n'efface pas l'inscription publique
    $yann = array_values(array_filter($a->all()['responsable_personnes'], fn($p) => $p['responsableId'] === $rid && $p['nom'] === 'Yann Saisi'))[0];
    $a->api(['action' => 'update', 'table' => 'responsables', 'id' => $rid, 'data' => ['personnes' => ['remove' => [$yann['id']]], 'creneauIds' => ['demo-c08']]]);
    $after = array_values(array_filter($a->all()['responsable_personnes'], fn($p) => $p['responsableId'] === $rid));
    eq(count($after), 1, 'personne saisie retirée');
    eq($after[0]['benevoleId'] ?? null, $s['json']['benevoleId'], 'inscription publique conservée');
    eq(array_values(array_map(fn($l) => $l['creneauId'], array_filter($a->all()['responsable_creneaux'], fn($l) => $l['responsableId'] === $rid))), ['demo-c08'], 'creneauIds remplace la liste');
    eq($a->api(['action' => 'update', 'table' => 'responsables', 'id' => $rid, 'data' => ['ficheUrl' => 'javascript:alert(1)']])['code'], 400, 'fiche de poste en javascript: refusée');
    eq($a->api(['action' => 'update', 'table' => 'responsables', 'id' => $rid, 'data' => ['personnes' => ['add' => [['nom' => '<script>']]]]])['code'], 400, 'nom de responsable avec < > refusé');
    // supprimer le bénévole libère la place
    $a->api(['action' => 'delete', 'table' => 'benevoles', 'id' => $s['json']['benevoleId']]);
    eq(count(array_filter($a->all()['responsable_personnes'], fn($p) => $p['responsableId'] === $rid)), 0, 'bénévole supprimé → place libérée');
});

/* ================================================================ 7. écritures organisateurs */

group('admin: validations et cascades', function () {
    $a = admin();
    eq($a->api(['action' => 'create', 'table' => 'postes', 'data' => ['eventId' => 'demo', 'nom' => 'X', 'couleur' => 9]])['code'], 400, 'couleur hors 0–7 refusée');
    eq($a->api(['action' => 'create', 'table' => 'creneaux', 'data' => ['eventId' => 'demo', 'posteId' => 'bar', 'jour' => '2027-07-10', 'debut' => '25:00', 'fin' => '26:00', 'besoin' => 1]])['code'], 400, 'heure invalide refusée');
    eq($a->api(['action' => 'create', 'table' => 'creneaux', 'data' => ['eventId' => 'demo', 'posteId' => 'bar', 'jour' => '2027-07-10', 'debut' => '10:00', 'fin' => '10:00', 'besoin' => 1]])['code'], 400, 'début = fin refusé');
    eq($a->api(['action' => 'create', 'table' => 'creneaux', 'data' => ['eventId' => 'demo', 'posteId' => 'bar', 'jour' => '2027-07-10', 'debut' => '10:00', 'fin' => '11:00', 'besoin' => 0]])['code'], 400, 'besoin < 1 refusé');
    eq($a->api(['action' => 'create', 'table' => 'events', 'data' => ['nom' => 'X', 'debut' => '2027-01-02', 'fin' => '2027-01-01']])['code'], 400, 'fin avant début refusée');
    eq($a->api(['action' => 'create', 'table' => 'events', 'data' => ['nom' => 'X', 'debut' => '2027-01-01', 'fin' => '2027-01-02', 'imageUrl' => 'http://x.fr/a.png']])['code'], 400, 'image en http refusée');
    eq($a->api(['action' => 'create', 'table' => 'affectations', 'data' => ['eventId' => 'demo', 'creneauId' => 'demo-c01', 'benevoleId' => 'inexistant']])['json']['error'] ?? null, 'conflict', 'clé étrangère inexistante refusée');
    eq($a->api(['action' => 'update', 'table' => 'postes', 'id' => 'inexistant', 'data' => ['nom' => 'X']])['code'], 404, 'mise à jour d\'un id inexistant → 404');
    eq($a->api(['action' => 'update', 'table' => 'postes', 'id' => 'bar', 'data' => []])['code'], 400, 'rien à modifier → 400');
    eq($a->api(['action' => 'update', 'table' => 'affectations', 'id' => 'x', 'data' => ['statut' => 'parti']])['code'], 400, 'statut inconnu refusé');
    // texte riche nettoyé à l'enregistrement
    $a->api(['action' => 'update', 'table' => 'events', 'id' => 'demo', 'data' => ['appel' => '<p onclick="x">Salut<script>alert(1)</script></p>']]);
    eq(by_id($a->all()['events'], 'demo')['appel'], '<p>Salut</p>', 'encadré d\'appel nettoyé côté serveur');
    // cascade : supprimer un poste supprime ses créneaux et leurs affectations
    $pid = create('postes', ['eventId' => 'demo', 'nom' => 'Éphémère', 'couleur' => 3]);
    $cid = create('creneaux', ['eventId' => 'demo', 'posteId' => $pid, 'jour' => '2027-07-10', 'debut' => '06:00', 'fin' => '07:00', 'besoin' => 3]);
    $bid = create('benevoles', ['prenom' => 'Cas', 'nom' => 'Cade']);
    create('affectations', ['eventId' => 'demo', 'creneauId' => $cid, 'benevoleId' => $bid, 'statut' => 'prevu']);
    eq($a->api(['action' => 'create', 'table' => 'affectations', 'data' => ['eventId' => 'demo', 'creneauId' => $cid, 'benevoleId' => $bid]])['json']['error'] ?? null, 'conflict', 'double affectation refusée');
    $a->api(['action' => 'delete', 'table' => 'postes', 'id' => $pid]);
    $all = $a->all();
    eq(by_id($all['creneaux'], $cid), null, 'cascade : créneau supprimé');
    eq(count(array_filter($all['affectations'], fn($x) => $x['creneauId'] === $cid)), 0, 'cascade : affectations supprimées');
    ok(by_id($all['benevoles'], $bid) !== null, 'le bénévole, lui, reste');
});

group('admin: historique des retraits', function () {
    $a = admin();
    $hist = fn(?string $bid = null) => array_values(array_filter($a->all()['historique'] ?? [], fn($h) => $bid === null || $h['benevoleId'] === $bid));
    ok(!array_key_exists('historique', client()->all()), 'public : pas d\'historique dans action=all');
    // bénévole inscrit puis retiré de son créneau : la fiche reste, le retrait est noté
    $s = signup(client(), 'demo-c02', ['prenom' => 'Histo', 'nom' => 'Rique', 'email' => 'histo@example.org', 'tel' => '06 98 76 54 32']);
    eq($s['code'], 201, 'inscription');
    $bid = $s['json']['benevoleId'];
    $aff = array_values(array_filter($a->all()['affectations'], fn($x) => $x['benevoleId'] === $bid))[0];
    eq($a->api(['action' => 'delete', 'table' => 'affectations', 'id' => $aff['id']])['json']['deleted'] ?? null, 1, 'retiré du créneau');
    $all = $a->all();
    ok(by_id($all['benevoles'], $bid) !== null, 'sans créneau, la fiche reste en base (annuaire des organisateurs)');
    eq(count(array_filter($all['affectations'], fn($x) => $x['benevoleId'] === $bid)), 0, 'plus aucune affectation');
    $h = $hist($bid);
    eq(count($h), 1, 'une ligne d\'historique');
    eq([$h[0]['action'], $h[0]['cause'], $h[0]['eventId'], $h[0]['email'], $h[0]['detail']['creneauId'] ?? null, $h[0]['detail']['statut'] ?? null],
       ['retrait', 'affectations', 'demo', 'histo@example.org', 'demo-c02', 'prevu'], 'retrait : créneau, statut et contact complet recopiés');
    ok(!empty($h[0]['detail']['poste']) && !empty($h[0]['detail']['inscritLe']), 'nom du poste et date d\'inscription gardés');
    // suppression d'un créneau : les affectations perdues sont notées
    $cid = create('creneaux', ['eventId' => 'demo', 'posteId' => 'bar', 'jour' => '2027-07-10', 'debut' => '07:00', 'fin' => '08:00', 'besoin' => 2]);
    create('affectations', ['eventId' => 'demo', 'creneauId' => $cid, 'benevoleId' => $bid, 'statut' => 'present']);
    $a->api(['action' => 'delete', 'table' => 'creneaux', 'id' => $cid]);
    $h = array_values(array_filter($hist($bid), fn($x) => ($x['detail']['creneauId'] ?? '') === $cid));
    eq([count($h), $h[0]['cause'] ?? null, $h[0]['detail']['statut'] ?? null], [1, 'creneaux', 'present'], 'créneau supprimé : retrait noté avec la cause');
    // poste de responsable : personne retirée, puis fiche supprimée
    $rid = create('responsables', ['eventId' => 'demo', 'titre' => 'Chef histo', 'besoin' => 2, 'personnes' => ['add' => [['benevoleId' => $bid], ['nom' => 'Saisi Histo', 'tel' => '0601020304']]]]);
    $saisi = array_values(array_filter($a->all()['responsable_personnes'], fn($p) => $p['responsableId'] === $rid && $p['nom'] === 'Saisi Histo'))[0];
    $a->api(['action' => 'update', 'table' => 'responsables', 'id' => $rid, 'data' => ['personnes' => ['remove' => [$saisi['id']]]]]);
    $h = array_values(array_filter($a->all()['historique'], fn($x) => $x['action'] === 'resp' && $x['nom'] === 'Saisi Histo'));
    eq([count($h), $h[0]['tel'] ?? null, $h[0]['detail']['titre'] ?? null], [1, '0601020304', 'Chef histo'], 'personne retirée d\'un poste de responsable : notée');
    create('affectations', ['eventId' => 'demo', 'creneauId' => 'demo-c03', 'benevoleId' => $bid]);
    $a->api(['action' => 'update', 'table' => 'benevoles', 'id' => $bid, 'data' => ['notes' => 'note privée']]);
    $a->api(['action' => 'delete', 'table' => 'benevoles', 'id' => $bid]);
    $all = $a->all();
    eq(by_id($all['benevoles'], $bid), null, 'fiche supprimée');
    $h = $hist($bid);
    $f = array_values(array_filter($h, fn($x) => $x['action'] === 'fiche'));
    eq([count($f), $f[0]['detail']['notes'] ?? null, $f[0]['detail']['email'] ?? null], [1, 'note privée', 'histo@example.org'], 'copie complète de la fiche supprimée (notes comprises)');
    eq(count(array_filter($h, fn($x) => $x['action'] === 'retrait' && $x['cause'] === 'benevoles')), 1, 'son créneau restant est noté (cause : fiche)');
    eq(count(array_filter($h, fn($x) => $x['action'] === 'resp' && $x['cause'] === 'benevoles')), 1, 'son poste de responsable est noté');
    eq(count($h), 5, 'rien n\'est perdu : 5 lignes au total pour ce bénévole');
    $r = $a->req('GET', 'export.php?format=json');
    ok(count(json_decode($r['body'], true)['historique'] ?? []) >= 5, 'historique inclus dans la sauvegarde JSON');
    eq($a->api(['action' => 'delete', 'table' => 'historique', 'id' => 'x'])['code'], 400, 'historique non modifiable par l\'API');
});

group('admin: exports', function () {
    $a = admin();
    $r = $a->req('GET', 'export.php?format=csv&event=demo');
    eq($r['code'], 200, 'export CSV');
    ok((bool)preg_match('~Content-Type: text/csv~i', $r['headers']), 'type text/csv', $r['headers']);
    contains($r['body'], 'Marie', 'le CSV contient les bénévoles inscrits');
    $r = $a->req('GET', 'export.php?format=json');
    $j = json_decode($r['body'], true);
    ok(is_array($j) && isset($j['events']['demo'], $j['creneaux']['demo-c01']), 'sauvegarde JSON complète (indexée par id)');
});

group('fichiers protégés', function () {
    global $SANDBOX;
    ok(is_file("$SANDBOX/data/.htaccess") && str_contains((string)file_get_contents("$SANDBOX/data/.htaccess"), 'Require all denied'), 'data/.htaccess présent (Require all denied)');
    ok(is_file("$SANDBOX/database/.htaccess"), 'database/.htaccess présent');
    ok(is_file("$SANDBOX/data/index.php"), 'data/index.php présent');
});

/* ---------------------------------------------------------------- bilan */

$log = is_file($serverLog) ? (string)file_get_contents($serverLog) : '';
if (preg_match_all('/PHP (Warning|Notice|Fatal error|Deprecated|Parse error):[^\n]*/', $log, $m)) {
    foreach (array_unique($m[0]) as $line) fail_test("journal du serveur : $line");
}

echo "\n" . str_repeat('─', 60) . "\n";
if ($T['fail']) {
    echo "\033[31m{$T['fail']} échec(s)\033[0m, {$T['pass']} réussi(s)\n";
    foreach ($T['failures'] as $f) echo "  - $f\n";
    exit(1);
}
echo "\033[32mTout passe : {$T['pass']} vérifications.\033[0m\n";
exit(0);
