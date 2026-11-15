<?php
declare(strict_types=1);

/**
 * Visitor numbers for the admin dashboard, read from Google Analytics 4
 * (Analytics Data API) with a service-account key. Cached for 10 minutes.
 * The key never leaves your computer and is never published.
 */

const GA_CACHE_SECONDS = 600;

function ga_configured(): bool
{
    return preg_match('/^\d{5,15}$/', trim(setting('ga_property_id'))) === 1 && trim(setting('ga_service_json')) !== '';
}

function ga_http(string $url, array $opts): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20] + curl_ca_options());
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = json_decode($body, true);
    if ($code < 200 || $code >= 300) {
        $msg = $json['error']['message'] ?? ($json['error_description'] ?? ($err ?: "HTTP $code"));
        throw new RuntimeException((string) $msg);
    }
    return is_array($json) ? $json : [];
}

function ga_access_token(): string
{
    $key = json_decode(setting('ga_service_json'), true);
    if (!is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
        throw new RuntimeException('The service-account key is not valid JSON. Paste the whole downloaded .json file.');
    }
    $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $now = time();
    $aud = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
        'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => $aud, 'iat' => $now, 'exp' => $now + 3600,
    ]));
    if (!openssl_sign($unsigned, $sig, $key['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Could not use the private key from the JSON file.');
    }
    $res = ga_http($aud, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($sig)]),
    ]);
    return (string) ($res['access_token'] ?? '');
}

/** Returns ['ok'=>true, 'live','today','week','month' => [users,views], 'countries','devices','sources' => [[name, users]...], 'fetched'] or ['ok'=>false,'error'=>...] */
function ga_stats(bool $refresh = false): array
{
    if (!ga_configured()) return ['ok' => false, 'error' => 'not_configured'];
    $cache = json_decode(setting('ga_cache'), true);
    if (!$refresh && is_array($cache) && ($cache['fetched'] ?? 0) > time() - GA_CACHE_SECONDS) return $cache;

    try {
        $token = ga_access_token();
        $pid = trim(setting('ga_property_id'));
        $hdr = [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POST => true];
        $api = "https://analyticsdata.googleapis.com/v1beta/properties/$pid";

        $month = ['startDate' => '27daysAgo', 'endDate' => 'today'];
        $top = fn(string $dim) => ['dateRanges' => [$month], 'dimensions' => [['name' => $dim]], 'metrics' => [['name' => 'activeUsers']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 5];
        $batch = ga_http("$api:batchRunReports", $hdr + [CURLOPT_POSTFIELDS => json_encode(['requests' => [
            ['dateRanges' => [
                ['startDate' => 'today', 'endDate' => 'today', 'name' => 'today'],
                ['startDate' => '6daysAgo', 'endDate' => 'today', 'name' => 'week'],
                $month + ['name' => 'month'],
            ], 'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']]],
            $top('country'), $top('deviceCategory'), $top('sessionSource'),
        ]])]);
        $reports = $batch['reports'] ?? [];

        $out = ['ok' => true, 'today' => [0, 0], 'week' => [0, 0], 'month' => [0, 0], 'fetched' => time()];
        foreach ($reports[0]['rows'] ?? [] as $row) {
            $range = $row['dimensionValues'][0]['value'] ?? '';
            if (isset($out[$range])) $out[$range] = [(int) $row['metricValues'][0]['value'], (int) $row['metricValues'][1]['value']];
        }
        foreach (['countries' => 1, 'devices' => 2, 'sources' => 3] as $k => $i) {
            $out[$k] = array_map(fn($r) => [$r['dimensionValues'][0]['value'], (int) $r['metricValues'][0]['value']], $reports[$i]['rows'] ?? []);
        }
        $rt = ga_http("$api:runRealtimeReport", $hdr + [CURLOPT_POSTFIELDS => json_encode(['metrics' => [['name' => 'activeUsers']]])]);
        $out['live'] = (int) ($rt['rows'][0]['metricValues'][0]['value'] ?? 0);

        save_setting('ga_cache', json_encode($out));
        return $out;
    } catch (Throwable $ex) {
        return ['ok' => false, 'error' => $ex->getMessage()];
    }
}
