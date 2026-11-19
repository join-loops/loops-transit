<?php

namespace App\Transit;

use Illuminate\Support\Facades\Http;

class RemoteFetcher
{
    public function json(string $url, string $accept = 'application/json'): ?array
    {
        $target = $this->resolve($url);
        $options = ['allow_redirects' => false];

        if ($target['ip'] !== null) {
            $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
            $options['curl'] = [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$ip]];
        }

        try {
            $response = Http::timeout((int) config('transit.http.timeout'))
                ->connectTimeout(5)
                ->withHeaders(['Accept' => $accept, 'User-Agent' => Transit::userAgent()])
                ->withOptions($options)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        $body = $response->body();

        if (! $response->ok() || strlen($body) > (int) config('transit.http.max_bytes')) {
            return null;
        }

        try {
            $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    public function resolve(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new TransitException('Only public https URLs can be fetched', 'unsafe_url');
        }

        $host = strtolower($parts['host']);
        $port = (int) ($parts['port'] ?? 443);

        if (config('transit.http.allow_private_networks')) {
            return ['host' => $host, 'port' => $port, 'ip' => null];
        }

        $literal = trim($host, '[]');
        $ips = filter_var($literal, FILTER_VALIDATE_IP) !== false ? [$literal] : $this->lookup($host);

        if ($ips === []) {
            throw new TransitException('The server could not be resolved', 'unresolvable');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new TransitException('The server does not resolve to a public address', 'unsafe_url');
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    protected function lookup(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
