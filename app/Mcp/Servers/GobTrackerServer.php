<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompaniesSearchTool;
use App\Mcp\Tools\CompanyShowTool;
use App\Mcp\Tools\ContractShowTool;
use App\Mcp\Tools\ContractsSearchTool;
use App\Mcp\Tools\LegislationSearchTool;
use App\Mcp\Tools\LegislationShowTool;
use App\Mcp\Tools\OfficialShowTool;
use App\Mcp\Tools\OfficialsSearchTool;
use App\Mcp\Tools\OrganizationShowTool;
use App\Mcp\Tools\OrganizationsSearchTool;
use App\Mcp\Tools\OrganizationStatsTool;
use App\Mcp\Tools\SearchEverywhereTool;
use App\Mcp\Tools\SubsidiesCallsSearchTool;
use App\Mcp\Tools\SubsidiesGrantsSearchTool;
use App\Mcp\Tools\SubsidyGrantShowTool;
use App\Mcp\Tools\TaxActivitiesSearchTool;
use App\Mcp\Tools\TaxParametersGetTool;
use App\Mcp\Tools\TaxPayrollCalculateTool;
use App\Mcp\Tools\TaxRegimesListTool;
use App\Mcp\Tools\TaxTypesListTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('GobTracker — Escáner Público')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Servidor MCP de GobTracker / Escáner Público — datos abiertos del sector público español.

CONTEXTO
Plataforma de transparencia que ingiere y normaliza datos oficiales del Estado español:
contratos públicos (PLACSP), subvenciones (BDNS), legislación (BOE), cargos públicos
(BOE Sección II.A), organismos contratantes (DIR3) y catálogo fiscal (regímenes IRPF/IVA/IS,
CNAE 2025, IAE, escalas progresivas, bases de Seguridad Social). Reutilización CC-BY 4.0.

QUÉ SE PUEDE HACER AQUÍ

  • CONTRATOS — buscar adjudicaciones por organismo, NUTS, NIF empresa o full-text;
    consultar ficha completa de un contrato con sus lotes, awards y modificaciones.

  • ORGANISMOS — buscar órganos de contratación, ficha con DIR3 y dirección postal,
    estadísticas agregadas (volumen contratado por estado y por año).

  • EMPRESAS — adjudicatarias del Estado por NIF; cuánto recibe cada una y de quién.

  • SUBVENCIONES — convocatorias BDNS y concesiones individuales (beneficiario,
    importe, base reguladora, mecanismo de financiación, MRR).

  • LEGISLACIÓN — disposiciones del BOE con identificador ELI europeo; buscador
    por sección, fecha, organismo emisor, tipo (Ley, RD, Orden, Resolución).

  • CARGOS PÚBLICOS — nombramientos y ceses extraídos del BOE Sección II.A,
    con trayectoria por persona y citas a la disposición original.

  • CATÁLOGO FISCAL — regímenes tributarios (IRPF/IVA/IS/SS) con compatibilidades,
    actividades económicas (CNAE 2025 + IAE), tipos impositivos por año/CCAA.

  • CALCULADORAS FISCALES — nómina (bruto→neto) con desglose línea a línea citando
    la legislación vigente. (Próximamente: factura, IRPF anual, IVA 303, modelo 130).

NORMAS DE USO

  1. Cifras reales — todo lo que devuelven las herramientas viene de los registros
     oficiales sincronizados a diario. No inventes ni redondees.

  2. Cita la fuente — cada respuesta lleva (cuando aplica) un campo `source_url`
     al BOE consolidado o al feed oficial. Pásalo al usuario final.

  3. Errores PLACSP — algunos awards muestran importes atípicos (>1.000M €) por
     erratas del feed XML. Las herramientas los marcan con `suspect_amount: true`;
     advierte al usuario antes de usarlos en estadísticas.

  4. Open data — al reutilizar resultados, cita "GobTracker · datos derivados de
     PLACSP/BDNS/BOE — CC-BY 4.0".

  5. Sin auth — la API es pública. El throttle es 100 req/min por IP.

EMPIEZA POR
Si el usuario pide algo genérico ("contratos del Ministerio de Hacienda"), comienza
por la herramienta `*_search` correspondiente con un filtro razonable y muestra los
3-5 primeros resultados antes de ir a fichas individuales.
TXT)]
class GobTrackerServer extends Server
{
    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        // Cross-módulo — punto de entrada universal
        SearchEverywhereTool::class,
        // Contratos
        ContractsSearchTool::class,
        ContractShowTool::class,
        // Organismos
        OrganizationsSearchTool::class,
        OrganizationShowTool::class,
        OrganizationStatsTool::class,
        // Empresas
        CompaniesSearchTool::class,
        CompanyShowTool::class,
        // Subvenciones (BDNS)
        SubsidiesCallsSearchTool::class,
        SubsidiesGrantsSearchTool::class,
        SubsidyGrantShowTool::class,
        // Legislación (BOE)
        LegislationSearchTool::class,
        LegislationShowTool::class,
        // Cargos públicos (BOE II.A)
        OfficialsSearchTool::class,
        OfficialShowTool::class,
        // Tax — catálogo
        TaxRegimesListTool::class,
        TaxActivitiesSearchTool::class,
        TaxTypesListTool::class,
        TaxParametersGetTool::class,
        // Tax — calculadora demo
        TaxPayrollCalculateTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
