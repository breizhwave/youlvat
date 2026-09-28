<?php
// Régie Bénévoles — configuration
// Générer un hash : php -r "echo password_hash('mon-mot-de-passe', PASSWORD_DEFAULT), PHP_EOL;"
// Tant qu'il est vide, l'administration est impossible (la consultation et l'inscription restent publiques).

return [
    'app_name'            => 'Régie Bénévoles',
    'admin_password_hash' => '',

    // SQLite par défaut. Pour MySQL : 'mysql:host=...;dbname=...;charset=utf8mb4' + db_user / db_pass.
    'dsn'     => 'sqlite:' . __DIR__ . '/data/regie.sqlite',
    'db_user' => null,
    'db_pass' => null,

    // Importe database/seed_kalanna2026.sql lors de la création de la base.
    'seed_on_create' => true,
];
