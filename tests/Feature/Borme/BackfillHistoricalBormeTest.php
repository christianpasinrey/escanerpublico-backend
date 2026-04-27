<?php

namespace Tests\Feature\Borme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Borme\Jobs\FetchBormeSumarioJob;
use Modules\Borme\Models\BormeIngestRun;
use Modules\Borme\Models\BormePdf;
use Tests\TestCase;

class BackfillHistoricalBormeTest extends TestCase
{
    use RefreshDatabase;

    public function test_enqueues_one_fetch_per_weekday_descending(): void
    {
        Bus::fake();

        $this->artisan('borme:backfill-historical', [
            '--from' => '2026-04-24',  // Friday
            '--stop-at' => '2026-04-20', // Monday
        ])->assertSuccessful();

        // 2026-04-20 Mon, 21 Tue, 22 Wed, 23 Thu, 24 Fri = 5 weekdays
        Bus::assertDispatched(FetchBormeSumarioJob::class, 5);
    }

    public function test_skips_weekends(): void
    {
        Bus::fake();

        $this->artisan('borme:backfill-historical', [
            '--from' => '2026-04-26',  // Sunday
            '--stop-at' => '2026-04-25', // Saturday
        ])->assertSuccessful();

        Bus::assertNotDispatched(FetchBormeSumarioJob::class);
    }

    public function test_skips_existing_dates_when_skip_existing_true(): void
    {
        BormePdf::create([
            'date' => '2026-04-22',
            'cve' => 'BORME-A-2026-77-28',
            'section' => 'A',
            'source_url' => 'https://example.test/already.pdf',
            'status' => 'parsed',
        ]);

        Bus::fake();

        $this->artisan('borme:backfill-historical', [
            '--from' => '2026-04-24',
            '--stop-at' => '2026-04-20',
        ])->assertSuccessful();

        // 5 weekdays - 1 (2026-04-22 already present) = 4 dispatched
        Bus::assertDispatched(FetchBormeSumarioJob::class, 4);
    }

    public function test_limit_caps_enqueueing(): void
    {
        Bus::fake();

        $this->artisan('borme:backfill-historical', [
            '--from' => '2026-04-24',
            '--stop-at' => '2026-01-01',
            '--limit' => 3,
        ])->assertSuccessful();

        Bus::assertDispatched(FetchBormeSumarioJob::class, 3);
    }

    public function test_creates_ingest_run_record(): void
    {
        Bus::fake();

        $this->artisan('borme:backfill-historical', [
            '--from' => '2026-04-24',
            '--stop-at' => '2026-04-20',
        ])->assertSuccessful();

        $run = BormeIngestRun::latest('id')->first();
        $this->assertSame('range', $run->type);
        $this->assertSame('2026-04-24', $run->to_date->toDateString());
        $this->assertSame('2026-04-20', $run->from_date->toDateString());
        $this->assertSame('completed', $run->status);
    }
}
