<?php

namespace App\Transit;

use Illuminate\Support\Facades\Cache;

class NodeInfoAdvertisement
{
    public const SCHEMAS = [
        'http://nodeinfo.diaspora.software/ns/schema/2.1',
        'http://nodeinfo.diaspora.software/ns/schema/2.0',
    ];

    public function __construct(protected RemoteFetcher $fetcher) {}

    public function advertises(string $host): bool
    {
        $key = 'transit:nodeinfo:'.sha1($host);
        $cached = Cache::get($key);

        if (is_bool($cached)) {
            return $cached;
        }

        $result = $this->check($host);

        Cache::put($key, $result, $result ? now()->addHour() : now()->addMinutes(2));

        return $result;
    }

    public function forget(string $host): void
    {
        Cache::forget('transit:nodeinfo:'.sha1($host));
    }

    protected function check(string $host): bool
    {
        try {
            $index = $this->fetcher->json('https://'.$host.'/.well-known/nodeinfo');
            $href = $this->schemaHref($index['links'] ?? null);

            if ($href === null || Transit::host($href) !== $host) {
                return false;
            }

            $nodeinfo = $this->fetcher->json($href);
        } catch (TransitException) {
            return false;
        }

        $listed = data_get($nodeinfo, 'metadata.loops.migration.transit');

        if (! is_array($listed)) {
            return false;
        }

        foreach ($listed as $url) {
            if (is_string($url) && Transit::sameUrl($url, Transit::url())) {
                return true;
            }
        }

        return false;
    }

    protected function schemaHref(mixed $links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach (self::SCHEMAS as $schema) {
            foreach ($links as $link) {
                if (is_array($link) && ($link['rel'] ?? null) === $schema && is_string($link['href'] ?? null)) {
                    return $link['href'];
                }
            }
        }

        return null;
    }
}
