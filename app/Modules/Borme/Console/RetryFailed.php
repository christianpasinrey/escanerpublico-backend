<?php

namespace Modules\Borme\Console;

use Illuminate\Console\Command;
use Modules\Borme\Jobs\ProcessBormePdfJob;
use Modules\Borme\Models\BormePdf;

class RetryFailed extends Command
{
    protected $signature = 'borme:retry-failed
        {--all : Also retry PDFs already in pending (in case the worker is stuck)}';

    protected $description = 'Reset failed BORME PDFs to pending and re-enqueue them on the borme queue.';

    public function handle(): int
    {
        $query = BormePdf::query()->where('status', 'failed');
        if ($this->option('all')) {
            $query->orWhere('status', 'pending');
        }

        $rows = $query->get(['id']);

        foreach ($rows as $row) {
            BormePdf::where('id', $row->id)->update([
                'status' => 'pending',
                'error_message' => null,
            ]);
            ProcessBormePdfJob::dispatch($row->id)->onQueue('borme');
        }

        $this->info("Re-queued {$rows->count()} PDF(s) onto queue 'borme'.");

        return self::SUCCESS;
    }
}
