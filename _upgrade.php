<?php

ini_set('max_execution_time', 600);

$this->log->info("UPGRADE | {$version} | begin");
$t = new timeExec();

// BEGIN code upgrade
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath) || !is_readable($configPath) || !is_writable($configPath)) {
        exit("config.php introuvable ou non éditable: $configPath\n");
    }

    $content = file_get_contents($configPath);
    if ($content === false) exit("Impossible de lire $configPath\n");

        // --- Helpers très simples ---
    function insert_before_php_close(string $content, string $toAdd): string {
        return preg_match('/\?>\s*$/', $content)
            ? preg_replace('/\?>\s*$/', $toAdd . "?>", $content)
            : $content . (str_ends_with($content, "\n") ? '' : "\n") . $toAdd;
    }

    function ensure_define_text(string &$content, string $name, string $phpValue): void {
        $pattern = '/^[ \t]*define\s*\(\s*[\'"]' . preg_quote($name,'/') . '[\'"]\s*,/mi';
        if (!preg_match($pattern, $content)) {
            $line = "define('{$name}', {$phpValue});\n";
            $content = insert_before_php_close($content, $line);
        }
    }

    function force_debug_false(string &$content): void {
        $pattern = '/^[ \t]*define\s*\(\s*[\'"]DEBUG[\'"]\s*,\s*(true|false)\s*\)\s*;.*$/mi';
        $replacement = "define('DEBUG', false); //default -> false";
        $new = preg_replace($pattern, $replacement, $content, -1, $count);
        $content = $new;
    }

    // --- Ajouts / modifs ---
    // (OK : on passe les strings déjà quotées ; les bool/num en brut)
    $version = isset($version) ? (string)$version : 'unknown';

    //si la version est antérieure à 1.13.0
    if (version_compare($version, '1.13.0', '<')) {
        // Sauvegarde simple
        $bkDir = __DIR__ . '/var/backups';
        @mkdir($bkDir, 0700, true);
        @copy($configPath, $bkDir . '/config-' . date('Ymd-His') . '.php');

        // Nettoyage des anciennes sauvegardes exposees a la racine
        foreach (glob(__DIR__ . '/config.php.bak-*') as $old) {
            @unlink($old);
        }

        // Defines à garantir
        ensure_define_text($content, 'REPO_VERSION_API', "'https://api.github.com/repos/domotrique/okovision_2023/releases/latest'");
        ensure_define_text($content, 'OKOVISION_VERSION', "'" . addslashes($version) . "'");
        ensure_define_text($content, 'OKV_ANALYTICS_ENABLED', '1');
        ensure_define_text($content, 'OKV_ANALYTICS_ENDPOINT', "'https://analytics.okostats.ovh/'");
    }

    // Test configuration Apache
    $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $scheme = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
    $probes = ['var/okv_ingest.json', '_logs/okovision.log', 'config.json'];
    $leaks  = [];

    foreach ($probes as $p) {
        $ch = curl_init("$scheme://$host/$p");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false, // certificat auto-signe frequent en LAN
        ]);
        curl_exec($ch);
        if (200 === (int) curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            $leaks[] = $p;
        }
        curl_close($ch);
    }

    @mkdir(__DIR__ . '/var', 0700, true);
    file_put_contents(__DIR__ . '/var/okv_security.json', json_encode([
        'checked_at' => time(),
        'status'     => $leaks ? 'exposed' : 'ok',
        'leaks'      => $leaks,
    ]));

    if ($leaks) {
        $this->log->fatal('UPGRADE | S-06 | fichiers sensibles exposes en HTTP : ' . implode(', ', $leaks)
            . ' - executer install/harden-apache.sh en root');
    }

    // Force DEBUG = false
    force_debug_false($content);

    // Écriture
    if (file_put_contents($configPath, $content) === false) {
        exit("Impossible d'écrire $configPath\n");
    }

    // END code upgrade
    $this->log->info("UPGRADE | {$version} | end");
?> 