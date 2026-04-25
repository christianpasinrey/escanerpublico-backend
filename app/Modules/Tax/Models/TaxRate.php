<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = [
        'tax_type_id',
        'year',
        'region_code',
        'rate',
        'base_min',
        'base_max',
        'fixed_amount',
        'conditions',
        'source_url',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'year' => 'integer',
        'rate' => 'decimal:4',
        'base_min' => 'decimal:2',
        'base_max' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'conditions' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class, 'tax_type_id');
    }
}
