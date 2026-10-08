<?php
class secret
{
    const PREFIX = 'enc:v1:';

    private static $_key;

    public static function generateKey()
    {
        return bin2hex(random_bytes(32));
    }

    public static function isEncrypted($stored)
    {
        return is_string($stored) && 0 === strpos($stored, self::PREFIX);
    }

    /**
     * Secret de config.php ; créé à la volée pour les installations existantes.
     */
    public static function key()
    {
        if (defined('OKV_SECRET_KEY')) {
            return OKV_SECRET_KEY;
        }
        if (null !== self::$_key) {
            return self::$_key;
        }

        $path = __DIR__.'/../config.php';
        if (!function_exists('sodium_crypto_secretbox') || !is_writable($path)) {
            return null;
        }

        $content = (string) file_get_contents($path);
        
        if (preg_match("/OKV_SECRET_KEY'\s*,\s*'([0-9a-f]{64})'/", $content, $m)) {
            return self::$_key = $m[1];
        }

        $key = self::generateKey();
        $line = "DEFINE('OKV_SECRET_KEY','".$key."');\n";
        $content = preg_match('/\?>\s*$/', $content)
            ? preg_replace('/\?>\s*$/', $line.'?>', $content)
            : $content."\n".$line;

        if (false === file_put_contents($path, $content, LOCK_EX)) {
            return null;
        }

        return self::$_key = $key;
    }

    public static function encrypt($plain, $hexKey = null)
    {
        $hexKey = $hexKey ?? self::key();
        if (null === $hexKey) {
            return null;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox((string) $plain, $nonce, self::derive($hexKey)));
    }

    public static function decrypt($stored, $hexKey = null)
    {
        $hexKey = $hexKey ?? self::key();
        if (null === $hexKey || !self::isEncrypted($stored)) {
            return '';
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if (false === $raw || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::derive($hexKey)
        );

        return false === $plain ? '' : $plain;
    }

    private static function derive($hexKey)
    {
        return sodium_crypto_generichash('okovision:pass_boiler:v1', hex2bin($hexKey), SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
