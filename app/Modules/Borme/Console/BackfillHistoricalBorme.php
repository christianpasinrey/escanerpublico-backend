<?php

namespace Modules\Borme\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Borme\Jobs\FetchBormeSumarioJob;
use Modules\Borme\Models\BormeIngestRun;
use Modules\Borme\Models\BormePdf;

class BackfillHistoricalBorme extends Command
{
    protected $signature = 'borme:backfill-historical
        {--from= : Newest date to enqueue (YYYY-MM-DD, defaults to today)}
        {--stop-at=2009-01-01 : Oldest date to enqueue (BORME digital archive starts 2009-01-01)}
        {--skip-existing=true : Skip days that already have BormePdf rows (idempotent re-runs)}
        {--limit= : Stop after enqueueing N days (for staged rollouts)}';

    protected $description = 'Enqueue BORME daily ingestion jobs going backwards from --from to --stop-at. Idempotent: re-running skips days already ingested. Worker (queue=borme, processes=1) drains the queue at its own pace; storage stays at one PDF in flight thanks to ProcessBormePdfJob unlinking in `finally`.';

    public function handle(): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : Carbon::today();
        $stopAt = Carbon::parse($this->option('stop-at'));
        $skipExisting = filter_var($this->option('skip-existing'), FILTER_VALIDATE_BOOLEAN);
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($from->lt($stopAt)) {
            $this->error("--from ({$from->toDateString()}) cannot be before --stop-at ({$stopAt->toDateString()}).");

            return self::FAILURE;
        }

        $existingDates = $skipExisting
            ? BormePdf::query()->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip()
            : collect();

        $run = BormeIngestRun::create([
            'type' => 'range',
            'from_date' => $stopAt->toDateString(),
            'to_date' => $from->toDateString(),
            'cursor_date' => $from->toDateString(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->info("Backfill run #{$run->id}: from {$from->toDateString()} → {$stopAt->toDateString()}");

        $enqueued = 0;
        $skipped = 0;
        $cursor = $from->copy();

        while ($cursor->gte($stopAt)) {
            if ($cursor->isWeekend()) {
                $cursor->subDay();

                continue;
            }

            $dateStr = $cursor->toDateString();

            if ($skipExisting && $existingDates->has($dateStr)) {
                $skipped++;
                $cursor->subDay();

                continue;
            }

            dispatch(new FetchBormeSumarioJob(str_replace('-', '', $dateStr), $run->id))
                ->onQueue('borme');
            $enqueued++;

            if ($enqueued % 50 === 0) {
                $this->line("  enqueued {$enqueued} ({$dateStr})");
            }

            if ($limit !== null && $enqueued >= $limit) {
                $this->warn("--limit reached at {$dateStr}");
                break;
            }

            $cursor->subDay();
        }

        $run->update([
            'cursor_date' => $cursor->toDateString(),
            'finished_at' => now(),
            'status' => 'completed',
        ]);

        $this->info("Done. Enqueued {$enqueued} day(s), skipped {$skipped} (already present). Worker drains queue 'borme' at its own pace.");

        return self::SUCCESS;
    }
}
