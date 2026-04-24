<?php

namespace Modules\Contracts\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Jobs\ProcessPlacspFile;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\PlacspStreamParser;

class ReprocessContracts extends Command
{
    protected $signature = 'contracts:reprocess
        {--run-id= : Reanudar un run existente (skip completados)}
        {--resume : Reanudar el último run failed/running}
        {--from= : Mes inicial YYYYMM (default: 201801)}
        {--to= : Mes final YYYYMM (default: mes actual)}
        {--atoms= : Lista explícita de paths separados por coma}
        {--parallel=4 : Jobs concurrentes}
        {--sync : Ejecutar inline (debugging)}
        {--dry-run : Enumerar + crear run, no dispatchar}
        {--fresh : Ejecutar migrate:fresh antes (pide confirmación)}';

    protected $description = 'Reprocesa atoms PLACSP desde storage local con tracking y resumibilidad';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (! $this->confirm('¿Seguro? Se ejecutará migrate:fresh, borrando TODO.')) {
                $this->warn('Cancelado.');

                return self::FAILURE;
            }
            $this->call('migrate:fresh');
        }

        // Always flush the resolver cache before running: stale orgs/companies
        // IDs in Redis would cause FK failures if the DB was reset between runs.
        // The in-memory cache is cheap to rebuild from DB via preload().
        Cache::tags(['placsp_import'])->flush();

        if ($this->option('resume')) {
            $run = ReprocessRun::whereIn('status', ['failed', 'running'])->latest()->first();
            if ($run === null) {
                $this->warn('No hay run para reanudar.');

                return self::SUCCESS;
            }

            return $this->executeRun($run);
        }

        if ($this->option('run-id')) {
            $run = ReprocessRun::findOrFail($this->option('run-id'));

            return $this->executeRun($run);
        }

        // New run
        $atoms = $this->enumerateAtoms();
        if ($atoms === []) {
            $this->error('No se encontraron atoms locales.');

            return self::FAILURE;
        }

        $this->info('Encontrados '.count($atoms).' atoms.');

        $run = ReprocessRun::create([
            'name' => 'Run '.now()->toDateTimeString(),
            'status' => 'pending',
            'total_atoms' => count($atoms),
            'config' => [
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'parallel' => (int) $this->option('parallel'),
                'atoms_explicit' => $this->option('atoms') ? true : false,
            ],
        ]);

        foreach ($atoms as $atomPath) {
            ReprocessAtomRun::create([
                'reprocess_run_id' => $run->id,
                'atom_path' => $atomPath,
                'atom_hash' => is_file($atomPath) ? (sha1_file($atomPath) ?: '') : '',
                'status' => 'pending',
            ]);
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN: run #'.$run->id.' creado con '.count($atoms).' atoms.');

            return self::SUCCESS;
        }

        return $this->executeRun($run);
    }

    private function executeRun(ReprocessRun $run): int
    {
        $run->update(['status' => 'running', 'started_at' => now()]);
        $pending = $run->atomRuns()->whereIn('status', ['pending', 'failed'])->get();

        $this->info('Procesando '.count($pending).' atoms del run #'.$run->id);
        $bar = $this->output->createProgressBar(count($pending));
        $bar->start();

        $parser = app(PlacspStreamParser::class);
        $ingestor = app(ContractIngestor::class);

        foreach ($pending as $atomRun) {
            if ($this->option('sync')) {
                try {
                    (new ProcessPlacspFile($atomRun->atom_path, $atomRun->id))->handle($parser, $ingestor);
                } catch (\Throwable $e) {
                    $this->error('Fallo: '.$atomRun->atom_path.' — '.$e->getMessage());
                }
            } else {
                ProcessPlacspFile::dispatch($atomRun->atom_path, $atomRun->id)
                    ->onQueue('contracts-reprocess');
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($this->option('sync')) {
            $this->finalizeRun($run);
        } else {
            $this->info('Jobs despachados. Monitoriza con `php artisan horizon` o `GET /api/internal/reprocess-runs/'.$run->id.'`');
        }

        return self::SUCCESS;
    }

    private function finalizeRun(ReprocessRun $run): void
    {
        $run->refresh();
        $anyFailed = $run->atomRuns()->where('status', 'failed')->exists();
        $run->update([
            'status' => $anyFailed ? 'failed' : 'completed',
            'finished_at' => now(),
            'processed_atoms' => $run->atomRuns()->where('status', 'completed')->count(),
            'total_entries' => (int) $run->atomRuns()->sum('entries_processed'),
            'failed_entries' => (int) $run->atomRuns()->sum('entries_failed'),
        ]);
    }

    /** @return string[] */
    private function enumerateAtoms(): array
    {
        $explicit = $this->option('atoms');
        if ($explicit) {
            return array_map('trim', explode(',', $explicit));
        }

        $from = $this->option('from') ?: '201801';
        $to = $this->option('to') ?: now()->format('Ym');

        $months = [];
        $cursor = \DateTime::createFromFormat('Ym', $from);
        $end = \DateTime::createFromFormat('Ym', $to);
        if ($cursor === false || $end === false) {
            return [];
        }
        while ($cursor <= $end) {
            $months[] = $cursor->format('Ym');
            $cursor->modify('+1 month');
        }

        $atoms = [];
        foreach ($months as $m) {
            $dir = storage_path("app/placsp/{$m}/extracted");
            if (! is_dir($dir)) {
                continue;
            }
            $found = glob($dir.'/*.atom');
            if ($found !== false) {
                $atoms = array_merge($atoms, $found);
            }
        }

        return $atoms;
    }
}
