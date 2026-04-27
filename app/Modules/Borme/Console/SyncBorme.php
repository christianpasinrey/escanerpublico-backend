<?php

namespace Modules\Borme\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Borme\Jobs\FetchBormeSumarioJob;
use Modules\Borme\Models\BormeIngestRun;

class SyncBorme extends Command
{
    protected $signature = 'borme:sync
        {--date= : Single date YYYY-MM-DD (defaults to yesterday)}
        {--from= : Range start YYYY-MM-DD (with --to)}
        {--to= : Range end YYYY-MM-DD (with --from)}
        {--sync : Process synchronously instead of queueing}';

    protected $description = 'Ingest BORME daily summaries: fetches the JSON sumario for each date and queues per-PDF parse jobs.';

    public function handle(): int
    {
        $dates = $this->resolveDates();
        if ($dates === []) {
            $this->error('No dates resolved — provide --date or --from/--to.');

            return self::FAILURE;
        }

        $run = BormeIngestRun::create([
            'type' => count($dates) === 1 ? 'daily' : 'range',
            'from_date' => $dates[0],
            'to_date' => end($dates),
            'cursor_date' => $dates[0],
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->info("Ingest run #{$run->id} dispatching for ".count($dates).' date(s).');

        foreach ($dates as $date) {
            $yyyymmdd = str_replace('-', '', $date);
            $job = new FetchBormeSumarioJob($yyyymmdd, $run->id);

            if ($this->option('sync')) {
                $this->line("  fetch {$date}...");
                dispatch_sync($job);
            } else {
                dispatch($job)->onQueue('borme');
                $this->line("  queued fetch {$date}");
            }
        }

        if ($this->option('sync')) {
            $run->update(['status' => 'completed', 'finished_at' => now()]);
        }

        return self::SUCCESS;
    }

    /**
     * @return string[] list of YYYY-MM-DD dates, ascending
     */
    private function resolveDates(): array
    {
        if ($this->option('date')) {
            return [$this->option('date')];
        }

        if ($this->option('from') && $this->option('to')) {
            $start = Carbon::parse($this->option('from'));
            $end = Carbon::parse($this->option('to'));
            $dates = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                if (! $d->isWeekend()) {
                    $dates[] = $d->toDateString();
                }
            }

            return $dates;
        }

        return [Carbon::yesterday()->toDateString()];
    }
}
