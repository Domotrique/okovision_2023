<?php

ini_set('max_execution_time', 600);

$this->log->info("UPGRADE | {$version} | begin");
$t = new timeExec();

// BEGIN code upgrade

try {
    $root = dirname(__DIR__); // adapte si besoin (si _upgrade est à la racine, mets __DIR__)
    $configPath = $root . '/config.php';
    $varDir     = $root . '/var';
    @mkdir($varDir, 0775, true);

    if (!file_exists($configPath)) {
        throw new RuntimeException("config.php not found at $configPath");
    }
    if (!is_writable($configPath)) {
        throw new RuntimeException("config.php not writable at $configPath");
    }

    // Charger la config courante pour tester les define existants
    // (on isole via scope pour éviter des "redefine")
    (static function ($configPath) {
        require $configPath;
    })($configPath);

    $appends = [];
    $report  = [];

    // Ajout des defines
    // REPO_VERSION_API
    // OKOVISION_VERSION
    // OKV_ANALYTICS_ENABLED
    $ensureDefine('REPO_VERSION_API', 'https://api.github.com/repos/domotrique/okovision_2023/releases/latest', $appends, $report);
    $ensureDefine('OKOVISION_VERSION', $version, $appends, $report);
    $ensureDefine('OKV_ANALYTICS_ENABLED', 'true', $appends, $report);

    // 2) Endpoint analytics si absent (ajuste l’URL si besoin)
    $defaultEndpoint = "'https://analytics.okostats.ovh/'";
    $ensureDefine('OKV_ANALYTICS_ENDPOINT', $defaultEndpoint, $appends, $report);

    if ($appends) {
        // Sauvegarde
        $backup = $configPath . '.bak-' . date('Ymd-His');
        if (!copy($configPath, $backup)) {
            throw new RuntimeException("Cannot create backup at $backup");
        }
        $this->log->info("UPGRADE | {$version} | config backup created", ['backup' => $backup]);

        // Écriture en append
        $content = file_get_contents($configPath);
        if ($content === false) {
            throw new RuntimeException("Cannot read config.php");
        }

        $footer .= implode("\n", $appends) . "\n";

        // Vérifie la présence de la balise de fin PHP
        if (preg_match('/\?>\s*$/', $content)) {
            // Insère juste avant
            $newContent = preg_replace('/\?>\s*$/', $footer . "?>", $content);
        } else {
            // Sinon, append en fin de fichier
            $newContent = $content . $footer;
        }

        $newContent_clean = preg_replace(
            "/define\s*\(\s*'DEBUG'\s*,\s*(true|false)\s*\)\s*;?.*/i",
            "define('DEBUG', false); //default -> false",
            $newContent
        );

        if (file_put_contents($configPath, $newContent_clean) === false) {
            throw new RuntimeException("Cannot write updated config.php");
        }
        $this->log->info("UPGRADE | {$version} | config updated", ['defines' => array_column($report, 'name')]);
    } else {
        $this->log->info("UPGRADE | {$version} | no changes needed (config already up to date)");
    }

} catch (Throwable $e) {
    $this->log->error("UPGRADE | {$version} | failed", ['error' => $e->getMessage()]);
    throw $e; // laisser remonter pour que l’installateur voie l’échec
}

// END code upgrade

$this->log->info("UPGRADE | {$version} | end :".$t->getTime());

?> 