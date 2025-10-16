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

    // Sauvegarde simple
    @copy($configPath, $configPath . '.bak-' . date('Ymd-His'));

    // Defines à garantir
    ensure_define_text($content, 'REPO_VERSION_API', "'https://api.github.com/repos/domotrique/okovision_2023/releases/latest'");
    ensure_define_text($content, 'OKOVISION_VERSION', "'" . addslashes($version) . "'");
    ensure_define_text($content, 'OKV_ANALYTICS_ENABLED', '1');
    ensure_define_text($content, 'OKV_ANALYTICS_ENDPOINT', "'https://analytics.okostats.ovh/'");

    // Force DEBUG = false
    force_debug_false($content);

    // Écriture
    if (file_put_contents($configPath, $content) === false) {
        exit("Impossible d'écrire $configPath\n");
    }

?> 