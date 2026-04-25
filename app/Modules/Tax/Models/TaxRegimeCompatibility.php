<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRegimeCompatibility extends Model
{
    protected $table = 'tax_regime_compatibility';

    protected $fillable = [
        'regime_a_id',
        'regime_b_id',
        'compatibility',
        'notes',
    ];

    public function regimeA(): BelongsTo
    {
        return $this->belongsTo(TaxRegime::class, 'regime_a_id');
    }

    public function regimeB(): BelongsTo
    {
        return $this->belongsTo(TaxRegime::class, 'regime_b_id');
    }
}
