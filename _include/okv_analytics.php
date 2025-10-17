<?php
    // okv_client_register.php — enrôlement d'une installation Okovision

    /**
     * Récupère l’identifiant unique de l’installation.
     * Génère et sauvegarde un nouvel identifiant si nécessaire.
     */
    function okv_get_install_id(): string {
        static $cached = null; // cache en mémoire
        if ($cached) return $cached;

        $data = okv_get_ingest_data();
        if (!empty($data['install_id'])) return $cached = $data['install_id'];

        // Création d’un nouvel UUID v4
        $bytes  = random_bytes(16);
        $bytes [6] = chr(ord($bytes [6]) & 0x0f | 0x40); // version 4
        $bytes [8] = chr(ord($bytes [8]) & 0x3f | 0x80); // variant
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes ), 4));
        okv_log('DEBUG', 'generated new INSTALL_ID', ['install_id' => $uuid]);

        $data['install_id'] = $uuid;
        okv_save_ingest_data($data);

        return $cached = $uuid;
    }

    function okv_get_ingest_data(): array {
        $path = __DIR__ . '/../var/okv_ingest.json';
        if (is_file($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data)) return $data;
        }
        return [];
    }

    /**
     * Sauvegarde les données d’ingestion dans le fichier okv_ingest.json.
     */
    function okv_save_ingest_data(array $data): bool {
        $path = __DIR__ . '/../var/okv_ingest.json';
        @mkdir(dirname($path), 0700, true);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($path, $json, LOCK_EX) !== false;
    }

    function okv_delete_token(): void {
        $data = okv_get_ingest_data();
        if (isset($data['token'])) {
            unset($data['token']);
            okv_save_ingest_data($data);
        }
    }

    /**
     * okv_http_json_post — effectue un POST JSON vers une URL et renvoie [code_http, réponse].
     *
     * @param string $url   URL cible (ex: https://analytics.okostats.ovh/register)
     * @param array  $data  Données à envoyer (tableau PHP → JSON)
     * @param array  $hdrs  En-têtes HTTP supplémentaires (optionnel)
     * @return array        [int $http_code, string $response_body]
     */
    function okv_http_json_post(string $url, array $data, array $hdrs = []): array {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = array_merge([
            'Content-Type: application/json',
            'Expect:' // évite "100-continue"
        ], $hdrs);

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

        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);

        if ($errno !== 0) {
            okv_log('ERROR', 'HTTP POST failed', ['url' => $url, 'errno' => $errno, 'error' => curl_error($ch)]);
        }

        curl_close($ch);
        return [$http, $resp ?: ''];
    }

    /** 
     * Enrôle l'installation auprès du serveur analytics si ce n'est pas déjà fait.
     * Renvoie le token d'authentification ou null en cas d'échec.
     */
    function okv_register_if_needed(): ?string {
        $data = okv_get_ingest_data();
        $install_id = okv_get_install_id();

        // Vérifie si un token est déjà enregistré localement
        if (!empty($data['token'])) {
            return $data['token'];
        }

        // Pas de token, on doit s'enregistrer
        [$http, $resp] = okv_http_json_post(
            OKV_ANALYTICS_ENDPOINT . 'register',
            ['install_id' => $install_id, 
            'ts' => time()]
        );

        okv_log('DEBUG', 'register payload', ['install_id' => $install_id]);

        if ($http !== 200 && $http !== 201) {
            okv_log('WARN', 'register failed', ['http' => $http, 'body' => substr((string)$resp, 0, 200)]);
            return null;
        }

        $payload = json_decode((string)$resp, true);
        if (!is_array($payload) || empty($payload['token'])) {
            okv_log('WARN', 'register response invalid', ['body' => $resp]);
            return null;
        }

        $data['token'] = $payload['token'];
        $data['registered_at'] = time();
        okv_save_ingest_data($data);

        okv_log('INFO', 'register success', ['install_id' => $install_id]);
        return $data['token'];
    }

    /**
     * Envoie la collecte (stats) vers l'endpoint /index.php
     * $fields permet de surcharger/ajouter des champs applicatifs.
     */
    function okv_send_stats(array $fields = []): bool {
        // Analytics désactivé ?
        if (!defined('OKV_ANALYTICS_ENABLED') || !OKV_ANALYTICS_ENABLED) {
            okv_log('INFO', 'analytics disabled, not sending stats');
            return false;
        }

        $data = okv_get_ingest_data();
        $install_id = okv_get_install_id();
        $token = $data['token'] ?? null;

        if (empty($token)) {
            $token = okv_register_if_needed();
            if (empty($token)) 
                okv_log('ERROR', 'cannot send stats, no token and registration failed');
                return false;
        }

        $payload = [
            'install_id'  => $install_id,
            'app_version' => defined('OKOVISION_VERSION') ? OKOVISION_VERSION : 'dev',
            'php_version' => PHP_VERSION,
            'ts'          => time(),
        ];
        okv_log('DEBUG', 'payload', $payload);

        $headers = [
            'Authorization: Bearer ' . $token,
            'X-Install-Id: ' . $install_id,
        ];

        [$http, $resp] = okv_http_json_post(
            OKV_ANALYTICS_ENDPOINT,
            $payload,
            $headers
        );

        if ($http === 403 || $http === 401) {
            okv_log('WARN', 'send unauthorized, rotating token', ['http' => $http]);
            if (okv_rotate_token()) {
                okv_log('INFO', 'token rotated, retrying send');
                return okv_send_stats($fields);
            }
            okv_log('WARN', 'install unknown on server; forcing re-register', ['http' => $http]);
            okv_delete_token();
            if (!okv_register_if_needed()) return false;
            return okv_send_stats($fields, true);
        }

        if ($http === 409) {
            okv_log('WARN', 'install_id mismatch, new registration necessary', ['http' => $http]);
            okv_delete_token();
            if (!okv_register_if_needed()) return false;
            return okv_send_stats($fields, true);
        }

        if ($http < 200 || $http >= 300) {
            okv_log('ERROR', 'send failed', ['http' => $http]);
            @error_log("[okv_analytics] send http=$http body=" . substr((string)$resp, 0, 200));
            return false;
        }
        okv_log('INFO', 'send success', ['http' => $http]);
        return true;
    }

    /**
     * Force la rotation du token d'authentification.
     * Utile si le token a été compromis ou si le serveur l'exige.
     * Renvoie true si la rotation a réussi, false sinon.
     */
    function okv_rotate_token(): bool {

        $ingest_data = okv_get_ingest_data();
        $token = $ingest_data['token'] ?? null;

        $payload = [
            'install_id' => okv_get_install_id(),
            'ts' => time(),
        ];

        $headers = [
            'Authorization: Bearer '.$token,
        ];

        [$http, $resp] = okv_http_json_post(
            OKV_ANALYTICS_ENDPOINT . 'rotate',
            $payload,
            $headers
        );

        if ($http!==200 || !$resp) {
            okv_log('WARN', 'rotate failed', ['http'=>$http]);
            return false;
        }
        $data = json_decode($resp,true); 
        
        if (empty($data['token'])) return false;

        $ingest_data['token'] = $data['token'];

        okv_save_ingest_data($ingest_data);
        
        okv_log('INFO', 'rotate success');
        return true;
    }

    /**
     * okv_log — logger minimaliste pour Okovision
     *
     * Écrit dans le journal d’erreurs PHP via error_log() avec un préfixe
     * standardisé. Le contexte est sérialisé en JSON compact.
     *
     * @param string $level  Niveau de log (ex: INFO, WARN, ERROR)
     * @param string $msg    Message court et explicite
     * @param array  $ctx    Contexte optionnel (tableau clé→valeur)
     */
    function okv_log(string $level, string $msg, array $ctx = []): void {
        $prefix = '[okv_analytics] ' . strtoupper($level) . ' ' . $msg;
        if (!empty($ctx)) {
            $json = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            @error_log($prefix . ' ' . $json);
        } else {
            @error_log($prefix);
        }
    }

    /**
     * Active ou désactive l'envoi de statistiques anonymes.
     * Modifie le fichier config.php pour ajouter ou mettre à jour
     * la ligne define('OKV_ANALYTICS_ENABLED', 1|0);
     * Renvoie true si l'opération a réussi, false sinon.
     */
    function okv_analytics_enable(bool $value): bool {
        $config_path = __DIR__ . '/../config.php';

        if (is_file($config_path)) {
            $content = file_get_contents($config_path);
            if (strpos($content, 'OKV_ANALYTICS_ENABLED') !== false) {
                $content = preg_replace("/define\('OKV_ANALYTICS_ENABLED',\s*(0|1)\)/", "define('OKV_ANALYTICS_ENABLED', " . ($value ? '1' : '0') . ")", $content);
            } else {
                $content .= "\ndefine('OKV_ANALYTICS_ENABLED', " . ($value ? '1' : '0') . ");\n";
            }
            file_put_contents($config_path, $content, LOCK_EX);
            return true;
        }

        return false;
    }
