<?php

ini_set('max_execution_time', 600);

$this->log->info("UPGRADE | {$version} | begin");
$t = new timeExec();

// BEGIN code upgrade

try {
    $root = __DIR__;
    $configPath = $root . '/config.php';

    if (!file_exists($configPath)) {
        throw new RuntimeException("config.php not found at $configPath");
    }
    if (!is_writable($configPath)) {
        throw new RuntimeException("config.php not writable at $configPath");
    }

    $content = file_get_contents($configPath);
    if ($content === false) {
        throw new RuntimeException("Cannot read config.php");
    }

    // --- Préparer ce qu'on veut garantir dans config.php (sans require) ---
    $appends = [];
    $report  = [];

    $ensureTextDefine = function (string $name, string $phpValue) use (&$appends, &$report, $findDefine, $noComments) {
        $found = $findDefine($noComments, $name);
        if (!$found['exists']) {
            $appends[] = "define('{$name}', {$phpValue});";
            $report[]  = ['action' => 'add', 'name' => $name, 'value' => $phpValue];
        } else {
            $report[]  = ['action' => 'keep', 'name' => $name, 'value' => $found['value']];
        }
    };

    // Ajout des defines (quote les strings)
    $ensureTextDefine('REPO_VERSION_API', "'https://api.github.com/repos/domotrique/okovision_2023/releases/latest'");
    $ensureTextDefine('OKOVISION_VERSION', "'" . addslashes((string)$version) . "'");
    $ensureTextDefine('OKV_ANALYTICS_ENABLED', 'true');

    // Endpoint analytics
    $ensureTextDefine('OKV_ANALYTICS_ENDPOINT', "'https://analytics.okostats.ovh/'");

    // Si on a des ajouts à faire, on construit le footer
    $footer = '';
    if ($appends) {
        $footer .= implode("\n", $appends) . "\n";
    }

    // Injecter le footer avant la balise de fin PHP si présente
    $newContent = $content;
    if ($footer !== '') {
        if (preg_match('/\?>\s*$/', $content)) {
            $newContent = preg_replace('/\?>\s*$/', $footer . "?>", $content);
        } else {
            $newContent = $content . $footer;
        }
    }

    // Forcer DEBUG à false (remplace seulement les lignes non commentées)
    $pattern = '/^[ \t]*define\s*\(\s*["\']DEBUG["\']\s*,\s*(true|false)\s*\)\s*;.*$/im';
    $replacement = "define('DEBUG', false); //default -> false";
    $replaced = preg_replace($pattern, $replacement, $newContent, -1, $count);
    $newContent = $replaced;

    // Écriture atomique pour éviter corruption
    $backup = $configPath . '.bak-' . date('Ymd-His');
    if (!copy($configPath, $backup)) {
        throw new RuntimeException("Cannot create backup at $backup");
    }
    $this->log->info("UPGRADE | {$version} | config backup created", ['backup' => $backup]);

    $perm = @fileperms($configPath) ?: 0644;
    $tmp  = $configPath . '.tmp-' . getmypid();
    if (file_put_contents($tmp, $newContent) === false) {
        @unlink($tmp);
        throw new RuntimeException("Cannot write temporary config");
    }
    @chmod($tmp, $perm);
    if (!@rename($tmp, $configPath)) {
        @unlink($tmp);
        throw new RuntimeException("Cannot replace config.php atomically");
    }

    $this->log->info("UPGRADE | {$version} | config updated", ['defines' => array_column($report, 'name')]);

} catch (Throwable $e) {
    $this->log->error("UPGRADE | {$version} | failed", ['error' => $e->getMessage()]);
    throw $e;
}

// END code upgrade

$this->log->info("UPGRADE | {$version} | end :".$t->getTime());

?> 