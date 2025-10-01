<?php
// include/okv_analytics.php
// Usage: require_once 'include/okv_analytics.php'; okv_analytics_maybe_send();


declare(strict_types=1);


// chemin local du fichier de config client (permissions 600)
if (!defined('OKV_ANALYTICS_CFG')) define('OKV_ANALYTICS_CFG', __DIR__ . '/../var/okv_analytics.json');


// --- utilitaires ----------------------------------------------------
// génère un UUIDv4
function okv_uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// --- config locale (JSON) --------------------------------------------
function okv_read_cfg(): array {
    $path = OKV_ANALYTICS_CFG;
    if (!is_file($path)) return [];
    $json = file_get_contents($path);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// ecrit config locale (JSON)
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
    $headers = ['Content-Type: application/json', 'Expect:'];
    if ($hmac !== null) { $headers[] = 'X-Signature: '.$hmac; }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,              // $jsonPayload DOIT être une chaîne JSON brute
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => OKV_ANALYTICS_TIMEOUT,
        CURLOPT_TIMEOUT        => OKV_ANALYTICS_TIMEOUT,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        // TLS strict (WAMP) — si OpenSSL, assure un cacert.pem via php.ini (cf. plus haut)
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // consider success only if HTTP 2xx returned
    if ($errno !== 0 || $http < 200 || $http >= 300) {
        error_log("[okv_analytics] errno=$errno http=$http err=$err");
        return false;
    }

    return true;
}


/**
 * Envoie les données analytiques si activé et si le délai minimal est écoulé.
 *
 * Lit/écrit la config locale dans var/okv_analytics.json
 * Ne fait rien si le fichier n'existe pas ou si l'opt-in n'est pas activé.
 *
 * @return void
 */
function okv_analytics_maybe_send(): void {
    // Lire config locale
    $cfg = okv_read_cfg();

    if (!is_file(OKV_ANALYTICS_CFG)) {
        // Première exécution : créer un fichier de config minimal (opt-in conservé)
        $cfg = ['analytics_enabled' => true];
        okv_ensure_install_id($cfg);
        okv_write_cfg($cfg); // crée var/ et le .json avec chmod 0600
    }

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