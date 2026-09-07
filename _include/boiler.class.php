<?php

class boiler
{
    const LOGFILES_PATH = '/logfiles/pelletronic';

    const SOCKET_TIMEOUT  = 1;
    const CONNECT_TIMEOUT = 5;
    const HTTP_TIMEOUT    = 10;

    // ['status' => 'ok'|'empty_ip'|'unreachable'|'no_logfiles'|'no_csv',
    //  'response' => bool, 'url' => string, 'http_code' => int, 'csv' => string[]]
    public static function check($address) {
        $r = [0];
        $address = trim($address);
        if ($address == null) {
            $r = [
                'status' => 'empty_ip', 
                'response' => false, 
                'url' => '', 
                'http_code' => 0, 
                'csv' => []
            ];
            return $r;
        }

        $tmp = explode(':', $address);
        $ip = $tmp[0];
        $port = isset($tmp[1]) ? $tmp[1] : 80;

        if ($fp = @fsockopen($ip, $port, $errCode, $errStr, self::SOCKET_TIMEOUT)) {
            @fclose($fp);
        } else {
            $r = [
                'status' => 'unreachable', 
                'response' => false, 
                'url' => '', 
                'http_code' => 0, 
                'csv' => []
            ];
            return $r;
        }

        $url = "http://" . $ip . self::LOGFILES_PATH;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_USERAGENT      => 'Okovision Agent',
        ]);
        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($html === false || $info['http_code'] != 200 || trim($html) === '') {
            $r = [
                'status' => 'no_logfiles', 
                'response' => false, 
                'url' => $url, 
                'http_code' => 0, 
                'csv' => []
            ];
        }

        $csv = self::findCsvLinks($html, $address);

        if ($csv == "") {
            $r = [
                'status' => 'no_csv', 
                'response' => true, 
                'url' => $url, 
                'http_code' => 200, 
                'csv' => []
            ];
        } else {
            $r = [
                'status' => 'ok', 
                'response' => true, 
                'url' => $url, 
                'http_code' => 200, 
                'csv' => $csv
            ];
        }

        return $r;
    }

    public static function findCsvLinks($html, $address) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $files = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));

            if ('' === $href || !preg_match("/touch.*\.csv$/i", $href)) {
                continue;
            }

            if (preg_match('#^https?://#i', $href)) {
                $url = $href;
            } elseif ('/' === $href[0]) {
                $url = 'http://'.$address.$href;
            } else {
                $url = 'http://'.$address.self::LOGFILES_PATH.'/'.$href;
            }

            $files[] = [
                'file' => trim(str_replace(self::LOGFILES_PATH.'/', '', $href)),
                'url'  => $url,
            ];
        }

        return $files;
    }
}