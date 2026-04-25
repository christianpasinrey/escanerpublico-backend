<?php

namespace Modules\Contracts\Services\Cache;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflarePurger
{
    /** @param  string[]  $urls */
    public function purgeUrls(array $urls): void
    {
        $token = config('cloudflare.api_token');
        $zone = config('cloudflare.zone_id');

        if (empty($token) || empty($zone) || empty($urls)) {
            return;
        }

        foreach (array_chunk($urls, 30) as $batch) {
            $resp = Http::withToken((string) $token)
                ->retry(3, 1000, throw: false)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
                    'files' => $batch,
                ]);

            if (! $resp->successful()) {
                Log::warning('Cloudflare purge failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
            }
        }
    }
}
