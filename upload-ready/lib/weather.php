<?php
declare(strict_types=1);

/**
 * Same-origin proxy for the IPMA daily forecast (Portimão), so the browser never
 * calls a third party and the page CSP can stay at connect-src 'self'.
 * No user or scheduling data is sent; the response is cached for 30 minutes.
 */
const WEATHER_IPMA_URL = 'https://api.ipma.pt/open-data/forecast/meteorology/cities/daily/1081100.json';
const WEATHER_CACHE_SECONDS = 1800;

function weather_fetch_remote(): ?string
{
    if (function_exists('curl_init')) {
        $curl = curl_init(WEATHER_IPMA_URL);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Care4All']);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return is_string($body) && $status === 200 ? $body : null;
    }
    $context = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'Care4All', 'follow_location' => 0]]);
    $body = @file_get_contents(WEATHER_IPMA_URL, false, $context);
    return is_string($body) ? $body : null;
}

function weather_api(): never
{
    require_auth();
    $cacheFile = dirname(DB_FILE) . '/ipma-cache.json';
    $cached = is_file($cacheFile) ? @file_get_contents($cacheFile) : false;
    $fresh = is_string($cached) && time() - (int) @filemtime($cacheFile) < WEATHER_CACHE_SECONDS;
    $body = $fresh ? $cached : weather_fetch_remote();
    $valid = is_string($body) && is_array(json_decode($body, true)['data'] ?? null);
    if ($valid && ! $fresh) {
        @file_put_contents($cacheFile, $body, LOCK_EX);
    }
    if (! $valid && is_string($cached)) {
        $body = $cached; // Serve the last good forecast while IPMA is unreachable.
        $valid = is_array(json_decode($cached, true)['data'] ?? null);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=600');
    if (! $valid) {
        http_response_code(503);
        echo json_encode(['error' => 'Previsão indisponível.']);
        exit;
    }
    echo $body;
    exit;
}
