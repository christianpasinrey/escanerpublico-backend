<?php

namespace Modules\Contracts\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Modules\Contracts\Jobs\ProcessPlacspFile;
use ZipArchive;

class SyncContracts extends Command
{
    protected $signature = 'contracts:sync
        {--month= : Mes a sincronizar (formato YYYYMM, ej: 202603). Por defecto el mes actual}
        {--all : Descargar todos los meses disponibles desde 2018}
        {--sync : Ejecutar el procesamiento de forma síncrona en lugar de por cola}
        {--force-download : Descargar aunque existan atoms locales extraídos}
        {--cleanup : Borrar atoms + extracted dir tras procesar cada mes (modo low-disk; requiere --sync)}';

    protected $description = 'Descarga y procesa contratos de la PLACSP (Plataforma de Contratación del Sector Público)';

    private string $baseUrl = 'https://contrataciondelsectorpublico.gob.es/sindicacion/sindicacion_643';

    public function handle(): int
    {
        // Limpiar resolver cache: si la BD fue wipeada (migrate:fresh) la cache Redis
        // tendría IDs huérfanos que rompen los FKs en contracts.organization_id.
        \Illuminate\Support\Facades\Cache::tags(['placsp_import'])->flush();

        $months = $this->resolveMonths();

        $this->info("Sincronizando {$months->count()} mes(es) de contratos PLACSP...");

        $bar = $this->output->createProgressBar($months->count());

        foreach ($months as $month) {
            $this->processMonth($month);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronización completada.');

        return self::SUCCESS;
    }

    protected function resolveMonths(): Collection
    {
        if ($this->option('all')) {
            $months = collect();
            $start = now()->setDate(2018, 1, 1)->startOfMonth();
            $end = now()->startOfMonth();
            while ($start <= $end) {
                $months->push($start->format('Ym'));
                $start->addMonth();
            }

            return $months;
        }

        $month = $this->option('month') ?? now()->format('Ym');

        return collect([$month]);
    }

    protected function processMonth(string $month): void
    {
        $zipFilename = "licitacionesPerfilesContratanteCompleto3_{$month}.zip";
        $url = "{$this->baseUrl}/{$zipFilename}";

        $dirPath = storage_path("app/placsp/{$month}");
        $zipPath = "{$dirPath}/{$zipFilename}";
        $extractPath = "{$dirPath}/extracted";

        // Local-first: si ya hay atoms extraídos y no se fuerza, saltar descarga.
        if (! $this->option('force-download') && is_dir($extractPath)) {
            $existingAtoms = glob("{$extractPath}/*.atom") ?: [];
            if ($existingAtoms !== []) {
                $this->line("  Atoms locales encontrados ({$month}): ".count($existingAtoms).' — saltando descarga.');
                foreach ($existingAtoms as $atomFile) {
                    if ($this->option('sync')) {
                        dispatch_sync(new ProcessPlacspFile($atomFile));
                    } else {
                        ProcessPlacspFile::dispatch($atomFile);
                    }
                }

                return;
            }
        }

        // Crear directorio
        if (! is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Descargar ZIP
        $this->line(" Descargando {$zipFilename}...");

        try {
            $response = Http::timeout(120)
                ->withHeaders(['User-Agent' => config('scrapers.user_agent', 'GobTracker/1.0')])
                ->get($url);

            if (! $response->successful()) {
                $this->warn("  No disponible: {$url} (HTTP {$response->status()})");

                return;
            }

            file_put_contents($zipPath, $response->body());
        } catch (\Throwable $e) {
            $this->error("  Error descargando {$url}: {$e->getMessage()}");

            return;
        }

        // Descomprimir (extractPath ya fue definido arriba en local-first check)
        if (! is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error("  Error abriendo ZIP: {$zipPath}");

            return;
        }

        $zip->extractTo($extractPath);
        $zip->close();

        // Despachar jobs para cada .atom
        $atomFiles = glob("{$extractPath}/*.atom");
        $this->line('  Encontrados '.count($atomFiles).' ficheros ATOM');

        foreach ($atomFiles as $atomFile) {
            if ($this->option('sync')) {
                dispatch_sync(new ProcessPlacspFile($atomFile));
            } else {
                ProcessPlacspFile::dispatch($atomFile);
            }
        }

        // Limpiar ZIP (conservar atoms para reprocesado)
        @unlink($zipPath);

        // --cleanup: borrar atoms + extracted dir tras procesar (modo low-disk).
        // Solo válido junto con --sync (en async, los jobs aún no se han ejecutado).
        if ($this->option('cleanup') && $this->option('sync')) {
            foreach ($atomFiles as $atomFile) {
                @unlink($atomFile);
            }
            @rmdir($extractPath);
            @rmdir($dirPath);
            $this->line("  🧹 Cleanup: atoms + extracted/{$month} eliminados.");
        }
    }
}
