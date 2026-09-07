<?php
/*
* Projet : Okovision 2023 - Supervision chaudiere OeKofen
* Auteur : Domotrique
* Utilisation commerciale interdite sans mon accord
*/

/**
 * Lecture et validation des entrées HTTP.
 * Toutes les méthodes sont tolérantes à la clé absente et renvoient
 * $default plutôt que de laisser passer une valeur non conforme.
 */
class input
{
    //Vérifie si la valeur fournie dans $src existe et est conforme
    public static function has($src, $key)
    {
        return is_array($src) && isset($src[$key]) && is_scalar($src[$key]) && '' !== trim((string) $src[$key]);
    }

    //Vérifie que la valeur est bien int
    public static function int($src, $key, $default = null)
    {
        if (!self::has($src, $key)) {
            return $default;
        }

        $v = trim((string) $src[$key]);

        return preg_match('/^-?[0-9]+$/', $v) ? (int) $v : $default;
    }

    //Vérifie que la valeur est bien float
    public static function num($src, $key, $default = null)
    {
        if (!self::has($src, $key)) {
            return $default;
        }

        $v = str_replace(',', '.', trim((string) $src[$key]));

        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * @param null|int $maxLen longueur de la colonne cible, null pour ne pas tronquer
     * Vérifie que la valeur est bien string
     */
    public static function str($src, $key, $default = '', $maxLen = null)
    {
        if (!self::has($src, $key)) {
            return $default;
        }

        $v = trim((string) $src[$key]);

        return (null === $maxLen) ? $v : mb_substr($v, 0, $maxLen);
    }

    /**
     * Date au format BDD (Y-m-d), strictement validée.
     */
    public static function date($src, $key, $default = null)
    {
        $v = self::str($src, $key, '', 10);
        $d = DateTime::createFromFormat('Y-m-d', $v);

        return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
    }

    //Vérifie que la valeur est bien int entre 1 et 12
    public static function month($src, $key, $default = null)
    {
        $v = self::int($src, $key);

        return (null !== $v && $v >= 1 && $v <= 12) ? $v : $default;
    }

    //Vérifie que la valeur est bien int entre 1970 et 2999
    public static function year($src, $key, $default = null)
    {
        $v = self::int($src, $key);

        return (null !== $v && $v >= 1970 && $v <= 2999) ? $v : $default;
    }

    /**
     * Timestamp Unix (colonnes int(11) unsigned).
     */
    public static function ts($src, $key, $default = null)
    {
        $v = self::int($src, $key);

        return (null !== $v && $v >= 0) ? $v : $default;
    }

    /**
     * Valeur d'une liste blanche, sinon $default.
     */
    public static function enum($src, $key, array $allowed, $default = null)
    {
        $v = self::str($src, $key, null);

        return (null !== $v && in_array($v, $allowed, true)) ? $v : $default;
    }
    
    /**
     * Valeur brute, sans trim ni troncature. À utiliser pour les secrets :
     * un mot de passe doit être pris exactement tel qu'il a été saisi.
     */
    public static function raw($src, $key, $default = null)
    {
        if (!is_array($src) || !isset($src[$key]) || !is_scalar($src[$key])) {
            return $default;
        }

        return (string) $src[$key];
    }
}
