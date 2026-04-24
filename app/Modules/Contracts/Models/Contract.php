<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function scopeStatus($query, string $s) { return $query->where('status_code', $s); }
    public function scopeTipo($query, string $t) { return $query->where('tipo_contrato_code', $t); }
    public function scopeProcedimiento($query, string $p) { return $query->where('procedimiento_code', $p); }
    public function scopeImporteMin($query, float $v) { return $query->where('importe_con_iva', '>=', $v); }
    public function scopeImporteMax($query, float $v) { return $query->where('importe_con_iva', '<=', $v); }

    // Relationships
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'awards')
            ->withPivot('amount', 'amount_without_tax', 'award_date', 'start_date',
                'formalization_date', 'contract_number', 'sme_awarded', 'num_offers', 'result_code')
            ->withTimestamps();
    }

    public function awards(): HasMany { return $this->hasMany(Award::class); }
    public function notices(): HasMany { return $this->hasMany(ContractNotice::class)->orderBy('issue_date'); }
    public function documents(): HasMany { return $this->hasMany(ContractDocument::class); }

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }
}
