<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRegime extends Model
{
    use HasFactory;

    protected $table = 'tax_regimes';

    protected $fillable = [
        'code',
        'scope',
        'name',
        'description',
        'requirements',
        'model_quarterly',
        'model_annual',
        'valid_from',
        'valid_to',
        'legal_reference_url',
        'source_hash',
        'editorial_md',
    ];

    protected $casts = [
        'requirements' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function obligations(): HasMany
    {
        return $this->hasMany(TaxRegimeObligation::class, 'regime_id');
    }

    public function compatibleRegimes(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'tax_regime_compatibility',
            'regime_a_id',
            'regime_b_id'
        )->withPivot('compatibility', 'notes');
    }
}
