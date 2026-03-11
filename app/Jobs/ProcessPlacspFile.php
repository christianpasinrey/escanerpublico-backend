<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\PlacspParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPlacspFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public string $filePath,
    ) {}

    public function handle(PlacspParser $parser): void
    {
        $content = file_get_contents($this->filePath);
        if (!$content) {
            Log::error("PLACSP: No se pudo leer {$this->filePath}");
            return;
        }

        $contracts = $parser->parseAtomFile($content);
        $upserted = 0;

        foreach ($contracts as $data) {
            if (empty($data['external_id']) || empty($data['expediente'])) {
                continue;
            }

            Contract::updateOrCreate(
                ['external_id' => $data['external_id']],
                array_merge($data, ['synced_at' => now()]),
            );
            $upserted++;
        }

        Log::info("PLACSP: Procesado {$this->filePath} — {$upserted} contratos upserted");
    }
}
