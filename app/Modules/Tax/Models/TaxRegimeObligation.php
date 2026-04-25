<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRegimeObligation extends Model
{
    protected $table = 'tax_regime_obligations';

    protected $fillable = [
        'regime_id',
        'model_code',
        'periodicity',
        'deadline_rule',
        'description',
        'electronic_required',
        'certificate_required',
        'draft_available',
        'valid_from',
        'valid_to',
        'source_url',
    ];

    protected $casts = [
        'electronic_required' => 'boolean',
        'certificate_required' => 'boolean',
        'draft_available' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function regime(): BelongsTo
    {
        return $this->belongsTo(TaxRegime::class, 'regime_id');
    }
}
