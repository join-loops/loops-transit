<?php

namespace App\Transit;

use Illuminate\Support\Facades\RateLimiter;

class HostPolicy
{
    public function __construct(protected NodeInfoAdvertisement $nodeinfo) {}

    public function admit(string $caller, array $hosts): void
    {
        foreach ($hosts as $host) {
            $this->assertAllowed($host);
        }

        $this->throttle($caller);

        if (! config('transit.require_advertised')) {
            return;
        }

        foreach ($hosts as $host) {
            if (! $this->nodeinfo->advertises($host)) {
                throw new TransitException($host.' does not list this Transit in its NodeInfo', 'not_advertised', 403);
            }
        }
    }

    public function assertAllowed(string $host): void
    {
        foreach ((array) config('transit.blocked_hosts') as $entry) {
            if ($this->matches($host, $entry)) {
                throw new TransitException($host.' is not accepted by this Transit', 'host_blocked', 403);
            }
        }

        $allowed = (array) config('transit.allowed_hosts');

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $entry) {
            if ($this->matches($host, $entry)) {
                return;
            }
        }

        throw new TransitException($host.' is not accepted by this Transit', 'host_not_allowed', 403);
    }

    protected function throttle(string $host): void
    {
        $key = 'transit:host:'.$host;

        if (RateLimiter::tooManyAttempts($key, max(1, (int) config('transit.rate_limits.host')))) {
            throw new TransitException('Too many requests from '.$host, 'rate_limited', 429);
        }

        RateLimiter::hit($key, 60);
    }

    protected function matches(string $host, string $entry): bool
    {
        $entry = ltrim(strtolower(trim($entry)), '*.');

        return $entry !== '' && ($host === $entry || str_ends_with($host, '.'.$entry));
    }
}
