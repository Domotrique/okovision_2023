<?php
// include/okv_analytics.php
// Usage: require_once 'include/okv_analytics.php'; okv_analytics_maybe_send();


declare(strict_types=1);


// --- CONFIG (à adapter) ---------------------------------------------
// chemin local du fichier de config client (permissions 600)
if (!defined('OKV_ANALYTICS_CFG')) define('OKV_ANALYTICS_CFG', __DIR__ . '/../var/okv_analytics.json');


// --- utilitaires ----------------------------------------------------
function okv_uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


function okv_read_cfg(): array {
    $path = OKV_ANALYTICS_CFG;
    if (!is_file($path)) return [];
    $json = file_get_contents($path);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function okv_write_cfg(array $data): bool {
    $path = OKV_ANALYTICS_CFG;
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $tmp = $path . '.tmp';
    $ok = file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    if ($ok === false) return false;
    chmod($tmp, 0600);
    return rename($tmp, $path);
}

// --- génération/persistance de l'install_id -------------------------
function okv_ensure_install_id(array &$cfg): void {
    if (!empty($cfg['install_id'])) return;
    $cfg['install_id'] = okv_uuidv4();
    $cfg['created_at'] = time();
}

// --- payload & signature -------------------------------------------
function okv_build_payload(array $cfg): array {
    return [
        'install_id' => $cfg['install_id'],
        'app_version' => defined('OKOVISION_VERSION') ? OKOVISION_VERSION : ($cfg['app_version'] ?? 'unknown'),
        'php_version' => PHP_VERSION,
        'firmware' => $cfg['firmware'] ?? 'unknown',
        'ts' => time(),
    ];
}

function okv_build_hmac(string $payloadJson): string {
    return hash_hmac('sha256', $payloadJson, OKV_ANALYTICS_HMAC_SECRET);
}

// --- envoi HTTP (fire-and-forget, timeout court) --------------------
function okv_send_payload(string $endpoint, string $jsonPayload, ?string $hmac = null): bool {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, OKV_ANALYTICS_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, OKV_ANALYTICS_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        $hmac !== null ? 'X-Signature: ' . $hmac : ''],
    );
    // ne pas suivre les redirections trop longtemps
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    // optionnel : forcer TLS >= 1.2
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // consider success only if HTTP 2xx returned
    if ($errno !== 0) return false;
    return ($http >= 200 && $http < 300);
}


// --- fonction principale : decide & envoie -------------------------
function okv_analytics_maybe_send(): void {
    // Lire config locale
    $cfg = okv_read_cfg();

    // Par défaut, desactiver (opt-in) si non explicite
    $enabled = $cfg['analytics_enabled'] ?? false;
    if (!$enabled) return; // nothing to do

    // ensure install_id
    okv_ensure_install_id($cfg);

    // rate-limit basique
    $last = (int)($cfg['last_ping'] ?? 0);
    if ($last > 0 && (time() - $last) < OKV_ANALYTICS_MIN_INTERVAL) return;

    // build payload
    $payload = okv_build_payload($cfg);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) return;

    $hmac = null;
    if (OKV_ANALYTICS_HMAC_ENABLED) {
        $hmac = okv_build_hmac($json);
    }

    // Attempt send (best-effort)
    $ok = false;
    try {
        $ok = okv_send_payload(OKV_ANALYTICS_ENDPOINT, $json, $hmac);
    } catch (Throwable $e) {
        $ok = false; // ignore
    }

    // update config last_ping et optional store
    $cfg['last_ping'] = time();
    if (!isset($cfg['app_version']) && defined('OKOVISION_VERSION')) $cfg['app_version'] = OKOVISION_VERSION;
    // don't store sensitive data — on garde juste les champs utilitaires
    okv_write_cfg($cfg);
}

// --- helper : activer/désactiver via script CLI ---------------------
function okv_analytics_enable(bool $on = true): bool {
    $cfg = okv_read_cfg();
    $cfg['analytics_enabled'] = $on ? true : false;
    if (empty($cfg['install_id'])) okv_ensure_install_id($cfg);
    return okv_write_cfg($cfg);
}