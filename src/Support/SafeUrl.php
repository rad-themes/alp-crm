<?php

namespace RadThemes\AlpCrm\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Guards outgoing webhook requests against server-side request forgery: a URL must be
 * http(s) and must resolve to a public address. The client handed back never follows
 * redirects and talks to the address that was checked, so a public URL can't bounce the
 * request to a private or local one.
 */
class SafeUrl
{
    public static function allowed(string $url): bool
    {
        return self::request($url) !== null;
    }

    /**
     * A client for a URL that passes the checks, or null when the URL is not allowed.
     */
    public static function request(string $url): ?PendingRequest
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $request = Http::timeout(10)->withoutRedirecting();

        if (config('alp-crm.allow_private_webhooks')) {
            return $request;
        }

        if (! defined('CURLOPT_RESOLVE')) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        // A host that doesn't resolve is refused rather than left to the HTTP client.
        if (! $ips) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip = str_contains($ips[0], ':') ? "[{$ips[0]}]" : $ips[0];

        return $request->withOptions([
            'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
        ]);
    }
}
