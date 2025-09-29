<?php
// tools/gen_okv_analytics.php
declare(strict_types=1);

// Emplacement du fichier JSON (adapte si besoin)
$path = realpath(__DIR__ . '/..') . '/var/okv_analytics.json';
$dir  = dirname($path);

// Génère un UUID v4
function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

// Crée le dossier var/ si besoin
if (!is_dir($dir)) {
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        fwrite(STDERR, "Erreur: impossible de créer $dir\n");
        exit(1);
    }
}

// Construit la config initiale
$config = [
    'analytics_enabled' => true,                        // opt-out ON par défaut
    'install_id'        => uuidv4(),
    'created_at'        => time(),
    'last_ping'         => null,
    'app_version'       => defined('OKOVISION_VERSION') ? OKOVISION_VERSION : null,
];

// Écrit de manière atomique
$tmp = $path . '.tmp';
$json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, "Erreur: json_encode a échoué\n");
    exit(1);
}
if (file_put_contents($tmp, $json) === false) {
    fwrite(STDERR, "Erreur: impossible d'écrire $tmp\n");
    exit(1);
}
chmod($tmp, 0600);
if (!rename($tmp, $path)) {
    fwrite(STDERR, "Erreur: impossible de renommer $tmp en $path\n");
    exit(1);
}

echo "OK: fichier créé -> $path\n";
