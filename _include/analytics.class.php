<?php
/**
 * analytics.php — Okovision Analytics Client
 *
 * Classe statique \Okovision\Analytics
 * Gère l’enrôlement, la persistance locale et l’envoi des statistiques
 *
 * Dépendances externes :
 *   - Constante OKV_ANALYTICS_ENDPOINT (ex: 'https://analytics.okostats.ovh/')
 *   - Constante OKV_ANALYTICS_ENABLED (booléen, optionnelle)
 *   - Constante OKOVISION_VERSION (optionnelle)
 */

namespace Okovision;

class analytics
{
    private const INGEST_FILE = '/../var/okv_ingest.json';

    /* ============================================================
     * Identifiant et persistance locale
     * ============================================================ */

    public static function getInstallId(): string
    {
        static $cached = null;
        if ($cached) return $cached;

        $data = self::getIngestData();
        if (!empty($data['install_id'])) {
            return $cached = $data['install_id'];
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // variant
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        self::log('DEBUG', 'generated new INSTALL_ID', ['install_id' => $uuid]);

        $data['install_id'] = $uuid;
        self::saveIngestData($data);

        return $cached = $uuid;
    }

    public static function getIngestData(): array
    {
        $path = __DIR__ . self::INGEST_FILE;
        if (is_file($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data)) return $data;
        }
        return [];
    }

    public static function saveIngestData(array $data): bool
    {
        $path = __DIR__ . self::INGEST_FILE;
        @mkdir(dirname($path), 0700, true);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($path, $json, LOCK_EX) !== false;
    }

    public static function deleteToken(): void
    {
        $data = self::getIngestData();
        if (isset($data['token'])) {
            unset($data['token']);
            self::saveIngestData($data);
        }
    }

    /* ============================================================
     * HTTP et communication avec le serveur Analytics
     * ============================================================ */

    /**
     * Envoie un POST JSON vers l’API Analytics
     * @param string $url URL de l’endpoint
     * @param array $data Données à envoyer (seront encodées en JSON)
     * @param array $extraHeaders En-têtes HTTP additionnels
     * @return array{0:int,1:string} Code HTTP et corps de la réponse
     */
    private static function httpJsonPost(string $url, array $data, array $extraHeaders = []): array
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = array_merge([
            'Content-Type: application/json',
            'Expect:', // évite "100-continue"
            'User-Agent: Okovision/Analytics-Client',
        ], $extraHeaders);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $resp  = curl_exec($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);

        if ($errno !== 0) {
            self::log('ERROR', 'HTTP POST failed', [
                'url'   => $url,
                'errno' => $errno,
                'error' => curl_error($ch),
            ]);
        }

        curl_close($ch);
        return [$http, $resp ?: ''];
    }

    /**
     * Enregistrement auprès du serveur Analytics
     * @return string|null Token d’authentification ou null en cas d’échec
     */

    public static function registerIfNeeded(): ?string
    {
        $data       = self::getIngestData();
        $install_id = self::getInstallId();

        // Déjà enregistré
        if (!empty($data['token'])) {
            return $data['token'];
        }

        // Vérification de la configuration
        if (!defined('OKV_ANALYTICS_ENDPOINT')) {
            self::log('ERROR', 'OKV_ANALYTICS_ENDPOINT not defined');
            return null;
        }

        // Envoyer la requête d’enregistrement
        [$http, $resp] = self::httpJsonPost(
            rtrim(OKV_ANALYTICS_ENDPOINT, '/') . '/register',
            ['install_id' => $install_id, 'ts' => time()]
        );

        // Échec HTTP
        if ($http !== 200 && $http !== 201) {
            self::log('WARN', 'register failed', ['http' => $http, 'body' => substr((string)$resp, 0, 200)]);
            return null;
        }

        // Analyse de la réponse
        $payload = json_decode((string)$resp, true);
        if (!is_array($payload) || empty($payload['token'])) {
            self::log('WARN', 'register response invalid', ['body' => $resp]);
            return null;
        }

        // Sauvegarde du token
        $data['token']        = $payload['token'];
        $data['registered_at'] = time();
        self::saveIngestData($data);

        self::log('INFO', 'register success', ['install_id' => $install_id]);
        return $data['token'];
    }

    /**
     * Rotation du token d’authentification
     * @return bool Succès ou échec de l’opération
     */
    public static function rotateToken(): bool
    {
        if (!defined('OKV_ANALYTICS_ENDPOINT')) {
            self::log('ERROR', 'OKV_ANALYTICS_ENDPOINT not defined');
            return false;
        }

        $ingest = self::getIngestData();
        $token  = $ingest['token'] ?? null;

        $payload = [
            'install_id' => self::getInstallId(),
            'ts'         => time(),
        ];

        $headers = ['Authorization: Bearer ' . $token];

        [$http, $resp] = self::httpJsonPost(
            OKV_ANALYTICS_ENDPOINT . 'rotate',
            $payload,
            $headers
        );

        if (($http !== 200 && $http !== 201) || !$resp) {
            self::log('WARN', 'rotate failed', ['http' => $http]);
            return false;
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['token'])) {
            self::log('WARN', 'rotate response invalid', ['body' => substr((string)$resp, 0, 200)]);
            return false;
        }

        $ingest['token'] = $data['token'];
        self::saveIngestData($ingest);

        self::log('INFO', 'rotate success');
        return true;
    }

    /**
     * Envoie des statistiques au serveur Analytics
     * @param array $fields Données additionnelles à envoyer
     * @return bool Succès ou échec de l’envoi
     */

    public static function sendStats(array $fields = []): bool
    {
        // Vérification de l’activation
        if (!defined('OKV_ANALYTICS_ENABLED') || !OKV_ANALYTICS_ENABLED) {
            self::log('INFO', 'analytics disabled, not sending stats');
            return false;
        }

        $data       = self::getIngestData();
        $install_id = self::getInstallId();
        $token      = $data['token'] ?? null;

        if (empty($token)) {
            $token = self::registerIfNeeded();
            if (empty($token)) {
                self::log('ERROR', 'cannot send stats, no token and registration failed');
                return false;
            }
        }

        $payload = array_merge([
            'install_id'  => $install_id,
            'app_version' => defined('OKOVISION_VERSION') ? OKOVISION_VERSION : 'dev',
            'php_version' => PHP_VERSION,
            'ts'          => time(),
        ], $fields);

        $headers = [
            'Authorization: Bearer ' . $token,
            'X-Install-Id: ' . $install_id,
        ];

        [$http, $resp] = self::httpJsonPost(
            rtrim(OKV_ANALYTICS_ENDPOINT, '/'),
            $payload,
            $headers
        );

        // Gestion des erreurs spécifiques
        // Unauthorized / Forbidden → rotation du token
        if ($http === 401 || $http === 403) {
            self::log('WARN', 'send unauthorized, rotating token', ['http' => $http]);
            if (self::rotateToken()) {
                self::log('INFO', 'token rotated, retrying send');
                return self::sendStats($fields);
            }
            self::deleteToken();
            if (!self::registerIfNeeded()) return false;
            return self::sendStats($fields);
        }

        // Conflict → install_id mismatch, nouvelle inscription nécessaire
        if ($http === 409) {
            self::log('WARN', 'install_id mismatch, new registration necessary', ['http' => $http]);
            self::deleteToken();
            if (!self::registerIfNeeded()) return false;
            return self::sendStats($fields);
        }

        // Échec HTTP générique
        if ($http < 200 || $http >= 300) {
            self::log('ERROR', 'send failed', ['http' => $http]);
            @error_log("[okv_analytics] send http=$http body=" . substr((string)$resp, 0, 200));
            return false;
        }

        self::log('INFO', 'send success', ['http' => $http]);
        return true;
    }

    /* ============================================================
     * Utilitaires
     * ============================================================ */

    /**
     * Journalisation
     * @param string $level Niveau de log (DEBUG, INFO, WARN, ERROR)
     * @param string $msg Message
     * @param array $ctx Contexte additionnel
     * @return void
     */
    public static function log(string $level, string $msg, array $ctx = []): void
    {
        $prefix = '[okv_analytics] ' . strtoupper($level) . ' ' . $msg;
        if (!empty($ctx)) {
            $json = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            @error_log($prefix . ' ' . $json);
        } else {
            @error_log($prefix);
        }
    }

    /**
     * Active ou désactive l’envoi des statistiques dans le fichier de configuration
     * @param bool $value true pour activer, false pour désactiver
     * @return bool Succès ou échec de l’opération
     */
    public static function setEnabled(bool $value): bool
    {
        $config_path = __DIR__ . '/../config.php';

        if (!is_file($config_path)) {
            return false;
        }

        $content = (string)file_get_contents($config_path);

        if (strpos($content, 'OKV_ANALYTICS_ENABLED') !== false) {
            $content = preg_replace(
                "/define\(\s*'OKV_ANALYTICS_ENABLED'\s*,\s*(0|1)\s*\)\s*;/",
                "define('OKV_ANALYTICS_ENABLED', " . ($value ? '1' : '0') . ");",
                $content
            );
        } else {
            $content .= "\ndefine('OKV_ANALYTICS_ENABLED', " . ($value ? '1' : '0') . ");\n";
        }

        return file_put_contents($config_path, $content, LOCK_EX) !== false;
    }
}
