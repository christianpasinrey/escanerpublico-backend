<?php

namespace Modules\Contracts\Console;

use Illuminate\Console\Command;
use Modules\Contracts\Services\Stats\LandingStatsService;

class RefreshLandingStats extends Command
{
    protected $signature = 'landing:refresh-stats';

    protected $description = 'Refresca los contadores de la landing (cache).';

    public function handle(LandingStatsService $stats): int
    {
        $stats->refresh();

        $this->info('Landing stats refrescados.');

        return self::SUCCESS;
    }
}
