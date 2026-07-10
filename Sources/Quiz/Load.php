<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Loads a URL using cURL or fsockopen with optional headers, caching, and session support.
 *
 * @license BSD
 * @see http://www.bin-co.com/php/scripts/load/
 *
 * @param string $url The URL to load.
 * @param array $options Request options.
 *
 * @return mixed
 */
function load(string $url, array $options = []): mixed
{
    $defaultOptions = [
        'method' => 'get',
        'return_info' => false,
        'return_body' => true,
        'cache' => false,
        'referer' => '',
        'headers' => [],
        'session' => false,
        'session_close' => false,
    ];

    foreach ($defaultOptions as $opt => $value) {
        if (!isset($options[$opt])) {
            $options[$opt] = $value;
        }
    }

    $urlParts = parse_url($url);
    $ch = false;
    $info = [
        'http_code' => 200,
    ];
    $response = '';
    $headers = [];

    if ($urlParts === false || !isset($urlParts['host'])) {
        return $options['return_info'] ? ['headers' => [], 'body' => '', 'info' => $info, 'curl_handle' => false] : '';
    }

    $sendHeader = [
        'Accept' => 'text/*',
        'User-Agent' => 'BinGet/1.00.A (http://www.bin-co.com/php/scripts/load/)',
    ] + $options['headers'];

    if ($options['cache']) {
        $cacheFolder = '/tmp/php-load-function/';
        if (isset($options['cache_folder'])) {
            $cacheFolder = (string) $options['cache_folder'];
        }
        if (!file_exists($cacheFolder)) {
            $oldUmask = umask(0);
            mkdir($cacheFolder, 0777);
            umask($oldUmask);
        }

        $cacheFileName = md5($url) . '.cache';
        $cacheFile = rtrim($cacheFolder, '/\\') . '/' . $cacheFileName;

        if (file_exists($cacheFile)) {
            $response = (string) file_get_contents($cacheFile);
            $separatorPosition = strpos($response, "\r\n\r\n");
            $headerText = substr($response, 0, $separatorPosition);
            $body = substr($response, $separatorPosition + 4);

            foreach (explode("\n", $headerText) as $line) {
                $parts = explode(': ', $line);
                if (count($parts) === 2) {
                    $headers[$parts[0]] = chop($parts[1]);
                }
            }
            $headers['cached'] = true;

            if (!$options['return_info']) {
                return $body;
            }

            return ['headers' => $headers, 'body' => $body, 'info' => ['cached' => true]];
        }
    }

    if (function_exists('curl_init') && (!isset($options['use']) || $options['use'] !== 'fsocketopen')) {
        if (isset($options['post_data'])) {
            $page = $url;
            $options['method'] = 'post';

            if (is_array($options['post_data'])) {
                $postData = [];
                foreach ($options['post_data'] as $key => $value) {
                    $postData[] = $key . '=' . urlencode((string) $value);
                }

                $urlParts['query'] = implode('&', $postData);
            } else {
                $urlParts['query'] = (string) $options['post_data'];
            }
        } else {
            if (isset($options['method']) && $options['method'] === 'post') {
                $page = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
            } else {
                $page = $url;
            }
        }

        if ($options['session'] && isset($GLOBALS['_binget_curl_session'])) {
            $ch = $GLOBALS['_binget_curl_session'];
        } else {
            $ch = curl_init($urlParts['host']);
        }

        curl_setopt($ch, CURLOPT_URL, $page) or die('Invalid cURL Handle Resouce');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, !$options['return_body']);
        if (isset($options['method']) && $options['method'] === 'post' && isset($urlParts['query'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $urlParts['query']);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, $sendHeader['User-Agent']);
        $customHeaders = ['Accept: ' . $sendHeader['Accept']];
        if (isset($options['modified_since'])) {
            $customHeaders[] = 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime((string) $options['modified_since']));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        if ($options['referer']) {
            curl_setopt($ch, CURLOPT_REFERER, $options['referer']);
        }

        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/binget-cookie.txt');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (isset($urlParts['user'], $urlParts['pass'])) {
            $customHeaders = ['Authorization: Basic ' . base64_encode($urlParts['user'] . ':' . $urlParts['pass'])];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        }

        $response = (string) curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($options['session'] && !$options['session_close']) {
            $GLOBALS['_binget_curl_session'] = $ch;
        } else {
            curl_close($ch);
        }
    } else {
        if (isset($urlParts['query'])) {
            if (isset($options['method']) && $options['method'] === 'post') {
                $page = $urlParts['path'];
            } else {
                $page = $urlParts['path'] . '?' . $urlParts['query'];
            }
        } else {
            $page = $urlParts['path'];
        }

        if (!isset($urlParts['port'])) {
            $urlParts['port'] = 80;
        }

        $fp = fsockopen($urlParts['host'], $urlParts['port'], $errno, $errstr, 30);
        if ($fp) {
            $out = '';
            if (isset($options['method']) && $options['method'] === 'post' && isset($urlParts['query'])) {
                $out .= "POST $page HTTP/1.1\r\n";
            } else {
                $out .= "GET $page HTTP/1.0\r\n";
            }

            $out .= "Host: {$urlParts['host']}\r\n";
            $out .= "Accept: {$sendHeader['Accept']}\r\n";
            $out .= "User-Agent: {$sendHeader['User-Agent']}\r\n";
            if (isset($options['modified_since'])) {
                $out .= 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime((string) $options['modified_since'])) . "\r\n";
            }

            $out .= "Connection: Close\r\n";

            if (isset($urlParts['user'], $urlParts['pass'])) {
                $out .= 'Authorization: Basic ' . base64_encode($urlParts['user'] . ':' . $urlParts['pass']) . "\r\n";
            }

            if (isset($options['method']) && $options['method'] === 'post' && !empty($urlParts['query'])) {
                $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out .= 'Content-Length: ' . strlen((string) $urlParts['query']) . "\r\n";
                $out .= "\r\n" . $urlParts['query'];
            }
            $out .= "\r\n";

            fwrite($fp, $out);
            while (!feof($fp)) {
                $response .= (string) fgets($fp, 128);
            }

            fclose($fp);
        }
    }

    if (($info['http_code'] ?? 200) === 404) {
        $body = '';
        $headers['Status'] = 404;
    } else {
        if (isset($info['header_size'])) {
            $headerText = substr($response, 0, $info['header_size']);
            $body = substr($response, $info['header_size']);
            foreach (explode("\n", $headerText) as $line) {
                $parts = explode(': ', $line);
                if (count($parts) === 2) {
                    $headers[$parts[0]] = chop($parts[1]);
                }
            }
        } else {
            $docStartPos = strpos($response, '<');
            $body = $docStartPos === false ? $response : substr($response, $docStartPos);
        }
    }

    if (isset($cacheFile)) {
        file_put_contents($cacheFile, $response);
    }

    if ($options['return_info']) {
        return ['headers' => $headers, 'body' => $body, 'info' => $info, 'curl_handle' => $ch];
    }

    return $body;
}
