<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * IP geolocation lookup for admins (the "pull up info about this IP" action).
 * Uses the free, keyless ip-api.com endpoint; cached per-IP for a day.
 */
class IpInfo
{
    public static function lookup(string $ip): ?array
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return Cache::remember('convoro.ipinfo.'.$ip, now()->addDay(), function () use ($ip) {
            try {
                $d = Http::timeout(6)
                    ->get('http://ip-api.com/json/'.$ip, ['fields' => 'status,country,regionName,city,isp,org,as,proxy,hosting,mobile,query'])
                    ->json();
                if (! is_array($d) || ($d['status'] ?? '') !== 'success') {
                    return null;
                }

                return [
                    'ip' => $d['query'] ?? $ip,
                    'location' => trim(implode(', ', array_filter([$d['city'] ?? '', $d['regionName'] ?? '', $d['country'] ?? ''])), ', '),
                    'isp' => $d['isp'] ?? ($d['org'] ?? ''),
                    'asn' => $d['as'] ?? '',
                    'flags' => array_keys(array_filter([
                        'proxy/VPN' => $d['proxy'] ?? false,
                        'hosting/datacenter' => $d['hosting'] ?? false,
                        'mobile' => $d['mobile'] ?? false,
                    ])),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
