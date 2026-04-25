<?php

namespace Tests\Feature\Contracts\Reprocess;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncContractsLocalFirstTest extends TestCase
{
    public function test_skips_download_when_atoms_already_extracted(): void
    {
        $month = '202601';
        $dir = storage_path("app/placsp/{$month}/extracted");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents("{$dir}/existing.atom", '<feed/>');

        Http::fake();

        try {
            $this->artisan('contracts:sync', ['--month' => $month]);

            Http::assertNothingSent();
        } finally {
            @unlink("{$dir}/existing.atom");
        }
    }

    public function test_force_download_ignores_local(): void
    {
        Http::fake(['*' => Http::response('zipdata', 200)]);

        $this->artisan('contracts:sync', ['--month' => '202602', '--force-download' => true]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sindicacion_643'));
    }
}
