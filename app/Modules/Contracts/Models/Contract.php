<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string|null $expediente
 * @property string|null $objeto
 * @property string|null $status_code
 * @property numeric|null $importe_sin_iva
 * @property numeric|null $importe_con_iva
 * @property numeric|null $valor_estimado
 * @property int|null $organization_id
 * @property \Carbon\CarbonImmutable|null $snapshot_updated_at
 */
class Contract extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cpv_codes' => 'array',
            'importe_sin_iva' => 'decimal:2',
            'importe_con_iva' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'duracion' => 'decimal:2',
            'garantia_porcentaje' => 'decimal:2',
            'fecha_presentacion_limite' => 'date',
            'fecha_disponibilidad_docs' => 'date',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'synced_at' => 'datetime',
            'mix_contract_indicator' => 'boolean',
            'over_threshold_indicator' => 'boolean',
            'snapshot_updated_at' => 'datetime',
            'annulled_at' => 'datetime',
        ];
    }

    public const STATUS_LABELS = [
        'PRE' => 'Anuncio previo', 'PUB' => 'En plazo',
        'EV' => 'Pendiente de adjudicación', 'ADJ' => 'Adjudicada',
        'RES' => 'Resuelta', 'ANUL' => 'Anulada',
    ];

    public const TIPO_LABELS = [
        '1' => 'Obras', '2' => 'Servicios', '3' => 'Suministros',
        '7' => 'Gestión de servicios públicos', '8' => 'Concesión de obras',
        '21' => 'Concesión de servicios', '31' => 'Colaboración público-privada',
        '40' => 'Administrativo especial', '50' => 'Privado',
    ];

    public const PROCEDIMIENTO_LABELS = [
        '1' => 'Abierto', '2' => 'Restringido',
        '3' => 'Negociado sin publicidad', '4' => 'Negociado con publicidad',
        '5' => 'Diálogo competitivo', '6' => 'Abierto simplificado',
        '100' => 'Basado en acuerdo marco', '999' => 'Otros',
    ];

    // Scopes
    public function scopeStatus(Builder $q, string $s): Builder
    {
        return $q->where('status_code', $s);
    }

    public function scopeTipo(Builder $q, string $t): Builder
    {
        return $q->where('tipo_contrato_code', $t);
    }

    public function scopeProcedimiento(Builder $q, string $p): Builder
    {
        return $q->where('procedimiento_code', $p);
    }

    public function scopeImporteMin(Builder $q, float $v): Builder
    {
        return $q->where('importe_con_iva', '>=', $v);
    }

    public function scopeImporteMax(Builder $q, float $v): Builder
    {
        return $q->where('importe_con_iva', '<=', $v);
    }

    public function scopeNotAnnulled(Builder $q): Builder
    {
        return $q->whereNull('annulled_at');
    }

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ContractLot::class)->orderBy('lot_number');
    }

    /**
     * Awards belong to lots now (v2). Use hasManyThrough to reach
     * all awards for any lot of this contract.
     */
    public function awards(): HasManyThrough
    {
        return $this->hasManyThrough(Award::class, ContractLot::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(ContractNotice::class)->orderBy('issue_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    public function modifications(): HasMany
    {
        return $this->hasMany(ContractModification::class)->orderBy('issue_date');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ContractSnapshot::class)->orderBy('entry_updated_at');
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }
}
