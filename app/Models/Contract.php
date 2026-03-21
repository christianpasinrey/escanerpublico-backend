<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cpv_codes' => 'array',
            'organo_jerarquia' => 'array',
            'criterios_adjudicacion' => 'array',
            'importe_sin_iva' => 'decimal:2',
            'importe_con_iva' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'importe_adjudicacion_sin_iva' => 'decimal:2',
            'importe_adjudicacion_con_iva' => 'decimal:2',
            'duracion' => 'decimal:2',
            'garantia_porcentaje' => 'decimal:2',
            'sme_awarded' => 'boolean',
            'fecha_presentacion_limite' => 'date',
            'fecha_disponibilidad_docs' => 'date',
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

    // Relationships
    public function notices(): HasMany
    {
        return $this->hasMany(ContractNotice::class)->orderBy('issue_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    // TODO: Reactivar Searchable trait cuando Typesense esté configurado
}
