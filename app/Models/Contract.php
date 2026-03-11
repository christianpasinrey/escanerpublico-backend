<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Contract extends Model
{
    use Searchable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cpv_codes' => 'array',
            'importe_sin_iva' => 'decimal:2',
            'importe_con_iva' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'importe_adjudicacion_sin_iva' => 'decimal:2',
            'importe_adjudicacion_con_iva' => 'decimal:2',
            'duracion' => 'decimal:2',
            'fecha_presentacion_limite' => 'date',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_adjudicacion' => 'date',
            'fecha_formalizacion' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    // Constantes de estado
    public const STATUS_PREVIO = 'PRE';
    public const STATUS_EN_PLAZO = 'PUB';
    public const STATUS_EVALUACION = 'EV';
    public const STATUS_ADJUDICADA = 'ADJ';
    public const STATUS_RESUELTA = 'RES';
    public const STATUS_ANULADA = 'ANUL';

    public const STATUS_LABELS = [
        'PRE' => 'Anuncio previo',
        'PUB' => 'En plazo',
        'EV' => 'Pendiente de adjudicación',
        'ADJ' => 'Adjudicada',
        'RES' => 'Resuelta',
        'ANUL' => 'Anulada',
    ];

    public const TIPO_LABELS = [
        '1' => 'Obras',
        '2' => 'Servicios',
        '3' => 'Suministros',
        '7' => 'Gestión de servicios públicos',
        '8' => 'Concesión de obras',
        '21' => 'Concesión de servicios',
        '31' => 'Colaboración público-privada',
        '40' => 'Administrativo especial',
        '50' => 'Privado',
    ];

    public const PROCEDIMIENTO_LABELS = [
        '1' => 'Abierto',
        '2' => 'Restringido',
        '3' => 'Negociado sin publicidad',
        '4' => 'Negociado con publicidad',
        '5' => 'Diálogo competitivo',
        '6' => 'Abierto simplificado',
        '100' => 'Basado en acuerdo marco',
        '999' => 'Otros',
    ];

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status_code', $status);
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo_contrato_code', $tipo);
    }

    public function scopeProcedimiento($query, string $proc)
    {
        return $query->where('procedimiento_code', $proc);
    }

    public function scopeImporteMin($query, float $min)
    {
        return $query->where('importe_con_iva', '>=', $min);
    }

    public function scopeImporteMax($query, float $max)
    {
        return $query->where('importe_con_iva', '<=', $max);
    }

    // Typesense search schema
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'expediente' => $this->expediente,
            'objeto' => $this->objeto,
            'organo_contratante' => $this->organo_contratante,
            'adjudicatario_nombre' => $this->adjudicatario_nombre ?? '',
            'adjudicatario_nif' => $this->adjudicatario_nif ?? '',
            'status_code' => $this->status_code,
            'tipo_contrato_code' => $this->tipo_contrato_code ?? '',
            'importe_con_iva' => (float) ($this->importe_con_iva ?? 0),
            'comunidad_autonoma' => $this->comunidad_autonoma ?? '',
            'fecha_adjudicacion' => $this->fecha_adjudicacion?->timestamp ?? 0,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    public function typesenseCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'fields' => [
                ['name' => 'expediente', 'type' => 'string'],
                ['name' => 'objeto', 'type' => 'string'],
                ['name' => 'organo_contratante', 'type' => 'string'],
                ['name' => 'adjudicatario_nombre', 'type' => 'string'],
                ['name' => 'adjudicatario_nif', 'type' => 'string'],
                ['name' => 'status_code', 'type' => 'string', 'facet' => true],
                ['name' => 'tipo_contrato_code', 'type' => 'string', 'facet' => true],
                ['name' => 'importe_con_iva', 'type' => 'float'],
                ['name' => 'comunidad_autonoma', 'type' => 'string', 'facet' => true],
                ['name' => 'fecha_adjudicacion', 'type' => 'int64'],
                ['name' => 'created_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'created_at',
        ];
    }
}
