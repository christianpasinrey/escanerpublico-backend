<?php

namespace Modules\Tax\Services;

use Illuminate\Support\Facades\DB;
use Modules\Legislation\Models\LegislationNorm;
use Modules\Tax\Models\TaxParameterAlert;

/**
 * Detector heurístico que monitoriza la tabla legislation_norms
 * (módulo Legislation) y crea alertas en tax_parameter_alerts cuando
 * detecta normas susceptibles de afectar a parámetros fiscales (IRPF,
 * IVA, IS, autónomos, cotizaciones SS).
 *
 * No abre issues GitHub: el flujo es interno (dashboard admin).
 */
class BoeParameterDetector
{
    /**
     * Patrones de detección. Cada entrada contiene:
     * - regex: expresión regular case-insensitive aplicada al título.
     * - action: descripción humana de la acción sugerida.
     * - tag: etiqueta corta usada en matched_pattern (idempotencia).
     *
     * @var list<array{regex: string, action: string, tag: string}>
     */
    private array $patterns;

    public function __construct()
    {
        $this->patterns = [
            // IRPF
            [
                'regex' => '/Real\s+Decreto.*Reglamento.*(?:Renta|IRPF).*Personas\s+F[ií]sicas/iu',
                'action' => 'Actualizar parámetros IRPF (mínimos, escalas, retenciones)',
                'tag' => 'irpf_reglamento',
            ],
            [
                'regex' => '/Ley.*Presupuestos\s+Generales.*Estado/iu',
                'action' => 'Revisar parámetros del año siguiente (PGE)',
                'tag' => 'pge_ley',
            ],
            [
                'regex' => '/(?:modific|reforma).*Ley.*35\/2006/iu',
                'action' => 'Revisar mínimos personales/familiares y escala IRPF (Ley 35/2006)',
                'tag' => 'irpf_ley_35_2006',
            ],

            // Cotización Seguridad Social
            [
                'regex' => '/Real\s+Decreto.*cotizaci[óo]n.*Seguridad\s+Social/iu',
                'action' => 'Actualizar bases y tipos cotización Seguridad Social',
                'tag' => 'ss_cotizacion',
            ],
            [
                'regex' => '/Orden.*bases.*cotizaci[óo]n/iu',
                'action' => 'Actualizar bases mínimas/máximas SS según orden anual',
                'tag' => 'ss_orden_bases',
            ],
            [
                'regex' => '/(?:RD-?ley|Real\s+Decreto-ley).*(?:aut[óo]nom|RETA|trabajo\s+propio)/iu',
                'action' => 'Revisar tramos cotización autónomos (RETA / RD-ley 13/2022 y prórrogas)',
                'tag' => 'autonomo_tramos',
            ],

            // IVA
            [
                'regex' => '/(?:Real\s+Decreto-ley|Ley).*(?:IVA|Impuesto.*Valor\s+A[ñn]adido)/iu',
                'action' => 'Revisar tipos IVA aplicables (general/reducido/superreducido/especial)',
                'tag' => 'iva_tipos',
            ],
            [
                'regex' => '/Ley\s+37\/1992/iu',
                'action' => 'Actualizar reglas IVA (Ley 37/1992)',
                'tag' => 'iva_ley_37_1992',
            ],

            // IS
            [
                'regex' => '/Real\s+Decreto.*Reglamento.*Impuesto.*Sociedades/iu',
                'action' => 'Revisar tipos y deducciones Impuesto sobre Sociedades',
                'tag' => 'is_reglamento',
            ],
            [
                'regex' => '/Ley\s+27\/2014/iu',
                'action' => 'Actualizar Impuesto sobre Sociedades (Ley 27/2014)',
                'tag' => 'is_ley_27_2014',
            ],

            // Mecanismo de Equidad Intergeneracional
            [
                'regex' => '/(?:MEI|equidad\s+intergeneracional)/iu',
                'action' => 'Actualizar tipo MEI (Mecanismo Equidad Intergeneracional)',
                'tag' => 'mei',
            ],
        ];
    }

    /**
     * Escanea legislation_norms y crea alertas para los matches nuevos.
     * Devuelve el número de alertas creadas.
     */
    public function scan(): int
    {
        $created = 0;

        $norms = LegislationNorm::query()
            ->whereNotNull('titulo')
            ->where('titulo', '!=', '')
            ->cursor();

        foreach ($norms as $norm) {
            $created += $this->scanSingle($norm);
        }

        return $created;
    }

    /**
     * Escanea una sola norma y genera las alertas correspondientes.
     * Útil tras ingest individual.
     */
    public function scanSingle(LegislationNorm $norm): int
    {
        if (empty($norm->titulo)) {
            return 0;
        }

        $created = 0;
        foreach ($this->patterns as $pattern) {
            if (! preg_match($pattern['regex'], (string) $norm->titulo)) {
                continue;
            }

            // Idempotencia: si ya existe alerta para esta norma+pattern, saltar.
            $exists = TaxParameterAlert::query()
                ->where('source_legislation_norm_id', $norm->id)
                ->where('matched_pattern', $pattern['tag'])
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                TaxParameterAlert::create([
                    'source_legislation_norm_id' => $norm->id,
                    'suggested_action' => $pattern['action'],
                    'status' => TaxParameterAlert::STATUS_PENDING,
                    'matched_pattern' => $pattern['tag'],
                    'notes' => sprintf(
                        'Detectado en BOE %s: "%s"',
                        $norm->external_id,
                        mb_substr((string) $norm->titulo, 0, 200),
                    ),
                ]);
                $created++;
            } catch (\Throwable) {
                // Race condition con índice único: ignorar.
            }
        }

        return $created;
    }

    /**
     * Devuelve los patrones registrados (para tests / introspección).
     *
     * @return list<array{regex: string, action: string, tag: string}>
     */
    public function patterns(): array
    {
        return $this->patterns;
    }

    /**
     * Cuenta alertas por estado (helper para reporting).
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return DB::table('tax_parameter_alerts')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
