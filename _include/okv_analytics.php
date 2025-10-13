<?php
    // okv_client_register.php — enrôlement d'une installation Okovision

    /** 
     * Fonctions pour l'enrôlement et l'envoi de statistiques anonymes vers un serveur tiers.
     * Basé sur un système d'UUID unique par installation.
     * Le serveur tiers doit implémenter un endpoint /register pour l'enrôlement
     * et un endpoint /index.php pour la collecte des stats.
     */
    function okv_get_install_id(): string {
        // Génère un UUID unique la première fois et le sauvegarde dans config.php sous forme de DEFINE.
        $config_path = __DIR__ . '/../config.php';

        // Si le fichier config.php contient déjà le DEFINE, on le lit et on le réutilise.
        if (is_file($config_path)) {
            $content = file_get_contents($config_path);
            if (preg_match("/define\('INSTALL_ID',\s*'([^']+)'\)/", $content, $m)) {
                return trim($m[1]);
            }
        }

        // Création d’un nouvel UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        okv_log('DEBUG', 'generated new INSTALL_ID', ['install_id' => $uuid]);

        // Ajoute le DEFINE dans config.php
        $define_line = "define('INSTALL_ID', '$uuid');\n";
        if (is_file($config_path)) {
            $content = file_get_contents($config_path);
            if (strpos($content, 'INSTALL_ID') === false) {
                file_put_contents($config_path, $content . "\n" . $define_line, LOCK_EX);
            }
        } else {
            okv_log('WARN', 'config.php not found, creating new one with INSTALL_ID');
            file_put_contents($config_path, "<?php\n$define_line", LOCK_EX);
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
        return 'https://analytics.okostats.ovh/index.php';
    }

    function okv_token_path(): string {
        return __DIR__ . '/../var/okv_ingest.json';
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

        if ($http !== 200 || !$resp) {
            okv_log('ERROR', 'register failed', ['http' => $http]);
            return null; // échec de la requête
        }

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['ok'])) {
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
        $url = rtrim($endpoint, '/') . '/register?action=rotate';

        $payload = json_encode(['install_id' => okv_get_install_id(), 'ts' => time()], JSON_UNESCAPED_SLASHES);
        $hdrs = ['Content-Type: application/json', 'Authorization: Bearer '.$old, 'Expect:'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_RETURNTRANSFER=>true]);
        $resp = curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
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
