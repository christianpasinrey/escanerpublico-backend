<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpOfficialResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Officials\Models\PublicOfficial;

#[Description(
    'Busca cargos públicos extraídos del BOE Sección II.A. Filtra por nombre '.
    '(full-text), por cargo desempeñado o por organismo donde se ejerce. La '.
    'cita al BOE original se obtiene en officials_show.'
)]
class OfficialsSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $cargo = $request->input('cargo');
        $organism = $request->input('organism');

        $query = PublicOfficial::query()
            ->select(['id', 'full_name', 'normalized_name', 'honorific',
                'appointments_count', 'first_appointment_date', 'last_event_date'])
            ->when($request->input('search'), function (Builder $q, $term) {
                $q->whereRaw('MATCH(full_name) AGAINST (? IN BOOLEAN MODE)', [$term]);
            });

        if ($cargo !== null && $cargo !== '') {
            $query->whereHas('appointments', fn (Builder $q) => $q->where('cargo', 'like', "%{$cargo}%"));
        }

        if ($organism !== null && $organism !== '') {
            $query->whereHas('appointments.organization', fn (Builder $q) => $q->where('name', 'like', "%{$organism}%"));
        }

        $rows = $query
            ->orderByDesc('last_event_date')
            ->limit($this->resolveLimit($request))
            ->get();

        // Decora con el último cargo/organismo conocido (1 query agrupada por persona).
        if ($rows->isNotEmpty()) {
            $ids = $rows->pluck('id')->all();
            $latest = DB::table('appointments')
                ->whereIn('public_official_id', $ids)
                ->leftJoin('organizations', 'organizations.id', '=', 'appointments.organization_id')
                ->orderByDesc('appointments.effective_date')
                ->orderByDesc('appointments.id')
                ->get([
                    'appointments.public_official_id as pid',
                    'appointments.cargo as cargo',
                    'organizations.name as organism',
                    'appointments.effective_date as effective_date',
                ])
                ->groupBy('pid');

            foreach ($rows as $r) {
                $first = $latest->get($r->id)?->first();
                if ($first !== null) {
                    $r->setAttribute('latest_cargo', $first->cargo);
                    $r->setAttribute('latest_organism', $first->organism);
                }
            }
        }

        return Response::json(McpResponseEnvelope::collection(
            McpOfficialResource::collection($rows),
            source: 'BOE — Sección II.A (nombramientos y ceses)',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Búsqueda full-text en el nombre completo (boolean mode MySQL).'),
            'cargo' => $schema->string()
                ->description('Texto del cargo (LIKE %cargo% sobre los appointments).'),
            'organism' => $schema->string()
                ->description('Nombre del organismo donde se ejerce el cargo (LIKE).'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
