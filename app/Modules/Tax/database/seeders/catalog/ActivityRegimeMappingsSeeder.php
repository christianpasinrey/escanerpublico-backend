<?php

namespace Modules\Tax\database\seeders\catalog;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;

/**
 * Mapping entre actividades económicas (CNAE/IAE) y regímenes tributarios elegibles,
 * con tipos por defecto de IVA y retención IRPF cuando aplica.
 *
 * Cubre las ~100 actividades CNAE/IAE más comunes en España. La fuente base es:
 *  - Orden HFP/1180/2024 (módulos 2025) para actividades en EO/IVA Simplificado
 *  - Art. 95 RD 439/2007 (Reglamento IRPF) para retenciones de profesionales
 *  - Anexo Ley 37/1992 (LIVA) para tipos reducidos y superreducidos
 *
 * Idempotente: usa updateOrCreate por activity_id (unique).
 */
class ActivityRegimeMappingsSeeder extends Seeder
{
    public function run(): void
    {
        // Mappings por CNAE-2025
        foreach ($this->cnaeMappings() as $row) {
            $this->seedMapping('cnae', $row);
        }

        // Mappings por IAE
        foreach ($this->iaeMappings() as $row) {
            $this->seedMapping('iae', $row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedMapping(string $system, array $row): void
    {
        $activity = EconomicActivity::query()
            ->where('system', $system)
            ->where('code', $row['code'])
            ->orderByDesc('year')
            ->first();

        if ($activity === null) {
            return;
        }

        ActivityRegimeMapping::query()->updateOrCreate(
            ['activity_id' => $activity->id],
            [
                'eligible_regimes' => $row['eligible_regimes'],
                'vat_rate_default' => $row['vat'] ?? null,
                'irpf_retention_default' => $row['irpf_ret'] ?? null,
                'notes' => $row['notes'] ?? null,
            ],
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function cnaeMappings(): iterable
    {
        // ============================================================
        // Hostelería (CNAE 55, 56)
        // ============================================================
        yield ['code' => '55100', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN', 'IS_ERD'], 'vat' => 10, 'irpf_ret' => null, 'notes' => 'Hoteles: IVA reducido 10 %.'];
        yield ['code' => '5510', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 10, 'notes' => 'Hoteles y alojamientos similares.'];
        yield ['code' => '551', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '56101', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Restaurantes: IVA reducido 10 %.'];
        yield ['code' => '56102', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '5610', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '561', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '56301', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Bares y cafeterías.'];
        yield ['code' => '5630', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '563', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 10];

        // ============================================================
        // Comercio al por menor (CNAE 47)
        // ============================================================
        yield ['code' => '47111', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_RE', 'IS_GEN', 'IS_ERD'], 'vat' => 21, 'notes' => 'Hipermercados: IVA según tipo de producto.'];
        yield ['code' => '47112', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_RE', 'IS_GEN', 'IS_ERD'], 'vat' => 21, 'notes' => 'Supermercados.'];
        yield ['code' => '47113', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Tiendas alimentación tradicionales.'];
        yield ['code' => '4711', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '471', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '472', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21];

        // ============================================================
        // Construcción (CNAE 41, 42, 43)
        // ============================================================
        yield ['code' => '41200', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_ISP', 'IS_GEN', 'IS_ERD'], 'vat' => 21, 'irpf_ret' => 1.00, 'notes' => 'Construcción de edificios. Retención IRPF 1 % si actividad incluida en módulos. ISP en B2B.'];
        yield ['code' => '4120', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '412', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '411', 'eligible_regimes' => ['EDN', 'IS_GEN', 'IS_ERD'], 'vat' => 21, 'notes' => 'Promoción inmobiliaria.'];
        yield ['code' => '41', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '42', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '43', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => 'F', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // ============================================================
        // Profesionales sanitarios (CNAE 86)
        // ============================================================
        yield ['code' => '86210', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ERD'], 'vat' => 0, 'irpf_ret' => 7.00, 'notes' => 'Medicina: actividad EXENTA de IVA. Retención profesionales IRPF 15 % (7 % en los 3 primeros años de inicio).'];
        yield ['code' => '8621', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00];
        yield ['code' => '86220', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00];
        yield ['code' => '8622', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00];
        yield ['code' => '86230', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00, 'notes' => 'Odontología: exenta de IVA.'];
        yield ['code' => '8623', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00];
        yield ['code' => '862', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00];
        yield ['code' => '861', 'eligible_regimes' => ['EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'notes' => 'Hospitales: exentos de IVA.'];

        // ============================================================
        // TIC y servicios profesionales (CNAE 62, 63, 69, 70, 71, 72, 73, 74)
        // ============================================================
        yield ['code' => '62010', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN', 'IS_ERD', 'IS_STARTUP'], 'vat' => 21, 'irpf_ret' => 7.00, 'notes' => 'Programación informática: 21 % IVA + retención profesional 15 % (7 % primeros 3 años).'];
        yield ['code' => '6201', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN', 'IS_STARTUP'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '62020', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN', 'IS_STARTUP'], 'vat' => 21, 'irpf_ret' => 7.00, 'notes' => 'Consultoría informática.'];
        yield ['code' => '6202', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN', 'IS_STARTUP'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '62090', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '6209', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '620', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '63', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => 'J', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // Profesionales jurídicos / contables
        yield ['code' => '69100', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Servicios jurídicos: retención 15 % (7 % primeros 3 años de inicio).'];
        yield ['code' => '6910', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '691', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '69200', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Asesoría fiscal/contable.'];
        yield ['code' => '6920', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '692', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];

        // Arquitectura e ingeniería
        yield ['code' => '71110', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Arquitectura: retención 15 % (7 % primeros 3 años).'];
        yield ['code' => '7111', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '71120', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Ingeniería técnica.'];
        yield ['code' => '7112', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '711', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '70', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Consultoría de gestión.'];
        yield ['code' => '73', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Publicidad y estudios de mercado.'];
        yield ['code' => '74', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => 'M', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];

        // ============================================================
        // Educación (CNAE 85)
        // ============================================================
        yield ['code' => '851', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0, 'notes' => 'Educación reglada: exenta de IVA.'];
        yield ['code' => '852', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0];
        yield ['code' => '853', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0];
        yield ['code' => '854', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0];
        yield ['code' => '855', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IVA_GEN', 'IS_GEN'], 'vat' => 0, 'notes' => 'Otra educación: exenta si reglada; 21 % si formación no oficial.'];
        yield ['code' => '85', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0];
        yield ['code' => 'P', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN', 'IS_ESFL'], 'vat' => 0];

        // ============================================================
        // Transporte (CNAE 49, 52, 53)
        // ============================================================
        yield ['code' => '49', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN', 'IS_ERD'], 'vat' => 10, 'irpf_ret' => 1.00, 'notes' => 'Transporte de viajeros: IVA reducido 10 %. Retención 1 % si actividad en módulos.'];
        yield ['code' => '52', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '53', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => 'H', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // ============================================================
        // Otros servicios personales (CNAE 96)
        // ============================================================
        yield ['code' => '96021', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00, 'notes' => 'Peluquería: actividad común en módulos.'];
        yield ['code' => '96022', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00, 'notes' => 'Salones de estética y belleza.'];
        yield ['code' => '9602', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '960', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // ============================================================
        // Inmobiliaria (CNAE 68)
        // ============================================================
        yield ['code' => '68', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IVA_GEN', 'IS_GEN', 'IS_SOCIMI'], 'vat' => 0, 'notes' => 'Alquiler de vivienda: exento. Alquiler de local comercial: 21 %.'];
        yield ['code' => 'L', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_EXENTO', 'IS_GEN', 'IS_SOCIMI'], 'vat' => 21];

        // ============================================================
        // Agricultura (CNAE 01, 02, 03)
        // ============================================================
        yield ['code' => '01', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_REAGP', 'IVA_GEN', 'AGRARIO', 'RETA'], 'vat' => 10, 'irpf_ret' => 2.00, 'notes' => 'Agricultura: REAGP por defecto, IVA reducido 10 %. Retención IRPF 2 % módulos / 1 % EDS.'];
        yield ['code' => '011', 'eligible_regimes' => ['EDS', 'EO', 'IVA_REAGP', 'AGRARIO', 'RETA'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '0111', 'eligible_regimes' => ['EDS', 'EO', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '01110', 'eligible_regimes' => ['EDS', 'EO', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '012', 'eligible_regimes' => ['EDS', 'EO', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '014', 'eligible_regimes' => ['EDS', 'EO', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '02', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_REAGP', 'IVA_GEN', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00];
        yield ['code' => '03', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 2.00, 'notes' => 'Pesca y acuicultura: REM en SS.'];
        yield ['code' => 'A', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_REAGP', 'AGRARIO'], 'vat' => 10];

        // ============================================================
        // Comercio al por mayor (CNAE 46)
        // ============================================================
        yield ['code' => '46', 'eligible_regimes' => ['EDN', 'EDS', 'IVA_GEN', 'IS_GEN', 'IS_ERD'], 'vat' => 21, 'notes' => 'Comercio al por mayor: IVA general 21 %.'];
        yield ['code' => 'G', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // ============================================================
        // Industria manufacturera (CNAE 10-33)
        // ============================================================
        yield ['code' => '10', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN', 'IS_ERD'], 'vat' => 4, 'notes' => 'Industria alimentación: tipo IVA depende del producto (4 % básicos, 10 % otros, 21 % bebidas alcohólicas).'];
        yield ['code' => '11', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'notes' => 'Bebidas: 21 % (incluso vino y cerveza).'];
        yield ['code' => '13', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '14', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '20', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '25', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '26', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN', 'IS_STARTUP'], 'vat' => 21];
        yield ['code' => '27', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '28', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '29', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];
        yield ['code' => 'C', 'eligible_regimes' => ['EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21];

        // Veterinaria
        yield ['code' => '75', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 10, 'irpf_ret' => 15.00, 'notes' => 'Servicios veterinarios: IVA reducido 10 %.'];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function iaeMappings(): iterable
    {
        // ============================================================
        // IAE Sección 1 (Empresariales)
        // ============================================================
        yield ['code' => '6471', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Comercio menor alimentación con vendedor.'];
        yield ['code' => '6472', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Autoservicio < 120 m².'];
        yield ['code' => '6473', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Supermercado 120-399,99 m².'];
        yield ['code' => '6511', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '6512', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Comercio menor textil/confección.'];
        yield ['code' => '6516', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Comercio menor calzado.'];
        yield ['code' => '6521', 'eligible_regimes' => ['EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 4, 'notes' => 'Farmacia: medicamentos al 4 %.'];
        yield ['code' => '6531', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Comercio menor de muebles.'];
        yield ['code' => '6532', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_RE', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '656', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_REBU', 'IS_GEN'], 'vat' => 21, 'notes' => 'Bienes usados: REBU disponible.'];

        // Restaurantes y bares
        yield ['code' => '6713', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Restaurante 3 tenedores.'];
        yield ['code' => '6714', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Restaurante 2 tenedores.'];
        yield ['code' => '6715', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Restaurante 1 tenedor.'];
        yield ['code' => '6731', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Bares categoría especial.'];
        yield ['code' => '6732', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10, 'notes' => 'Otros cafés y bares.'];
        yield ['code' => '6721', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '6722', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '6723', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];

        // Hospedaje
        yield ['code' => '681', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN', 'IS_ERD'], 'vat' => 10];
        yield ['code' => '682', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '684', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN', 'IS_SOCIMI'], 'vat' => 10];
        yield ['code' => '685', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 10];

        // Construcción IAE
        yield ['code' => '5011', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '5041', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00, 'notes' => 'Instalaciones eléctricas.'];
        yield ['code' => '5042', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '5051', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];
        yield ['code' => '5053', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_ISP', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00];

        // Transporte
        yield ['code' => '7211', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '7212', 'eligible_regimes' => ['EDS', 'EO', 'IVA_GEN', 'IVA_SIMPLE'], 'vat' => 10, 'irpf_ret' => 1.00, 'notes' => 'Autotaxis: módulos típicos.'];
        yield ['code' => '7213', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 10];
        yield ['code' => '722', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 1.00, 'notes' => 'Transporte de mercancías por carretera.'];

        // Servicios personales
        yield ['code' => '9721', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Peluquería caballero/señora.'];
        yield ['code' => '9722', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21, 'notes' => 'Estética y belleza.'];

        // Servicios reparación
        yield ['code' => '6911', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21];
        yield ['code' => '6912', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IVA_SIMPLE', 'IS_GEN'], 'vat' => 21];

        // Educación / Sanidad / Belleza
        yield ['code' => '9421', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_EXENTO', 'IS_GEN'], 'vat' => 0, 'irpf_ret' => 7.00, 'notes' => 'Estomatología/odontología.'];
        yield ['code' => '9331', 'eligible_regimes' => ['EDS', 'EDN', 'EO', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'notes' => 'Autoescuelas.'];
        yield ['code' => '9671', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN', 'IS_ESFL'], 'vat' => 21, 'notes' => 'Instalaciones deportivas.'];

        // Inmuebles
        yield ['code' => '8611', 'eligible_regimes' => ['EDS', 'IVA_EXENTO'], 'vat' => 0, 'notes' => 'Alquiler vivienda: exento de IVA.'];
        yield ['code' => '8612', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN', 'IS_SOCIMI'], 'vat' => 21, 'notes' => 'Alquiler local comercial: 21 % IVA.'];

        // Servicios B2B
        yield ['code' => '8499', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 7.00];
        yield ['code' => '844', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'notes' => 'Publicidad y RRPP.'];

        // ============================================================
        // IAE Sección 2 (Profesionales)
        // ============================================================
        yield ['code' => '751', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Profesionales publicidad y RRPP.'];
        yield ['code' => '8993', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IVA_CAJA', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00, 'notes' => 'Profesionales auxiliares servicios financieros/jurídicos.'];
        yield ['code' => '8994', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'], 'vat' => 21, 'irpf_ret' => 15.00];
        yield ['code' => '01', 'eligible_regimes' => ['EDS', 'EDN', 'IVA_REAGP', 'IS_GEN', 'AGRARIO'], 'vat' => 10, 'irpf_ret' => 7.00, 'notes' => 'Profesionales agrícolas (IAE Sec 2).'];
    }
}
