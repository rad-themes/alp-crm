<?php

namespace RadThemes\RadpackCrm\Support;

/**
 * Guards outgoing webhook requests against server-side request forgery:
 * URLs must be http(s) and must not point at private, loopback or reserved addresses.
 */
class SafeUrl
{
    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        if (config('radpack-crm.allow_private_webhooks')) {
            return true;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
