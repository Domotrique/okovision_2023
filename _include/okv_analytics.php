<?php
    // okv_client_register.php — enrôlement d'une installation Okovision

    /**
     * Récupère ou génère un identifiant unique pour cette installation.
     * Le premier appel crée un UUID v4 et l'enregistre dans config.php
     * sous la forme d'un DEFINE('INSTALL_ID', '...').
     * Les appels suivants renvoient la même valeur.
     */
    function okv_get_install_id(): string {
        // Si déjà défini, on le renvoie
        if (defined('INSTALL_ID')) {
            return INSTALL_ID;
        }

        // Création d’un nouvel UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        okv_log('DEBUG', 'generated new INSTALL_ID', ['install_id' => $uuid]);

        // Ajoute le DEFINE dans config.php
        $define_line = "define('INSTALL_ID', '$uuid');\n";
        $config_path = __DIR__ . '/../config.php';
        if (is_file($config_path)) {
            $content = file_get_contents($config_path);
            if (strpos($content, 'INSTALL_ID') === false) {
                if (preg_match('/\?>\s*$/', $content)) {
                    $content = preg_replace('/\?>\s*$/', "\n$define_line?>", $content);
                } else {
                    $content .= "\n$define_line";
                }
                file_put_contents($config_path, $content, LOCK_EX);
            }
        }

        okv_log('INFO', 'new INSTALL_ID generated', ['install_id' => $uuid]);
        return $uuid;
    }

    function okv_get_endpoint(): string {
        // Récupère l'endpoint analytics depuis config.php, sinon valeur par défaut
        $config_path = __DIR__ . '/../config.php';

        $content = file_get_contents($config_path);
        if (preg_match("/define\('OKV_ANALYTICS_ENDPOINT',\s*'([^']+)'\)/", $content, $m)) {
            okv_log('DEBUG', 'OKV_ANALYTICS_ENDPOINT found in config', ['endpoint' => $m[1]]);
            return trim($m[1]);
        }
        okv_log('WARN', 'OKV_ANALYTICS_ENDPOINT not defined, using default');
        return 'https://analytics.okostats.ovh/';
    }

    function okv_token_path(): string {
        return __DIR__ . '/../var/okv_ingest.json';
    }

    function okv_delete_token(): void {
        @unlink(okv_token_path());
    }

    /** 
     * Enrôle l'installation auprès du serveur analytics si ce n'est pas déjà fait.
     * Renvoie le token d'authentification ou null en cas d'échec.
     */
    function okv_register_if_needed(): ?string {
        $path = okv_token_path();

        // Si le token existe déjà, on le renvoie
        if (is_file($path)) {
            $j = json_decode((string)@file_get_contents($path), true);
            if (!empty($j['token'])) {
                okv_log('DEBUG', 'already registered', ['token' => substr($j['token'], 0, 4) . '...']);
                return (string)$j['token'];
            }
            okv_log('WARN', 'token file exists but no token');
        }

        $install_id = okv_get_install_id();
        $endpoint = okv_get_endpoint();
        $payload = json_encode(['install_id' => $install_id, 'ts' => time()], JSON_UNESCAPED_SLASHES);
        okv_log('DEBUG', 'register payload', ['install_id' => $install_id]);

        // Appel HTTPS vers le serveur analytics (endpoint défini dans config.php)
        $url = rtrim($endpoint, '/') . '/register';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Expect:'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http < 200 || $http >= 300) {
            okv_log('ERROR', 'register failed (http)', ['http' => $http, 'body' => substr((string)$resp, 200)]);
            return null;
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            okv_log('ERROR', 'register failed', ['http' => $http, 'body' => substr((string)$resp, 0, 200)]);
            return null;
        }

        // Nouveau token reçu
        if (!empty($data['token'])) {
            @mkdir(dirname($path), 0700, true);
            file_put_contents($path, json_encode(['token' => $data['token']], JSON_PRETTY_PRINT));
            @chmod($path, 0600);
            okv_log('INFO', 'register success', ['install_id' => $install_id]);
            return (string)$data['token'];
        }

        // déjà enrôlé côté serveur, on espère avoir le token localement
        okv_log('WARN', 'register returned ok but no token');
        return $j['token'] ?? null;
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

        $install_id = okv_get_install_id();
        $token      = okv_register_if_needed();
        if (!$token) { return false; }

        $endpoint = okv_get_endpoint();

        // Champs par défaut
        $payload = [
            'install_id'  => $install_id,
            'app_version' => defined('OKOVISION_VERSION') ? OKOVISION_VERSION : 'dev',
            'php_version' => PHP_VERSION,
            'ts'          => time(),
        ];
        okv_log('DEBUG', 'payload', $payload);
        // Merge avec champs custom
        foreach ($fields as $k => $v) { $payload[$k] = $v; }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token, // token d'authentification
            'X-Install-Id: ' . $install_id,
            'Expect:',
        ];

        $ch = curl_init($endpoint); // POST direct sur /index.php
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno= curl_errno($ch);
        curl_close($ch);

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

        if ($errno !== 0 || $http < 200 || $http >= 300) {
            okv_log('ERROR', 'send failed', ['http' => $http, 'errno' => $errno]);
            @error_log("[okv_analytics] send errno=$errno http=$http body=" . substr((string)$resp, 0, 200));
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
        $path = okv_token_path();
        $j = is_file($path) ? json_decode((string)@file_get_contents($path), true) : [];
        $old = $j['token'] ?? null; if (!$old) return false;

        $endpoint = okv_get_endpoint();
        $url = rtrim($endpoint, '/') . '/rotate';

        $payload = json_encode(['install_id' => okv_get_install_id(), 'ts' => time()], JSON_UNESCAPED_SLASHES);
        $hdrs = ['Content-Type: application/json', 'Authorization: Bearer '.$old, 'Expect:'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_RETURNTRANSFER=>true]);
        $resp = curl_exec($ch); 
        $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); 
        curl_close($ch);
        if ($http!==200 || !$resp) {
            okv_log('WARN', 'rotate failed', ['http'=>$http]);
            return false;
        }
        $data = json_decode($resp,true); if (empty($data['ok'])||empty($data['token'])) return false;
        file_put_contents($path, json_encode(['token'=>$data['token']], JSON_PRETTY_PRINT)); @chmod($path,0600);
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
