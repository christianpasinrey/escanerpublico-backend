<?php

namespace Modules\Contracts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Contracts\Services\Cache\CloudflarePurger;

class PurgeContractUrls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /** @param  int[]  $contractIds */
    public function __construct(public array $contractIds) {}

    public function handle(CloudflarePurger $purger): void
    {
        $base = rtrim((string) config('cloudflare.base_url'), '/');
        $urls = [];
        foreach ($this->contractIds as $id) {
            $urls[] = "{$base}/api/v1/contracts/{$id}";
        }
        $urls[] = "{$base}/api/v1/contracts";

        $purger->purgeUrls($urls);
    }
}
