<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;

class TaxBracket extends Model
{
    protected $table = 'tax_brackets';

    protected $fillable = [
        'year',
        'scope',
        'region_code',
        'type',
        'from_amount',
        'to_amount',
        'rate',
        'fixed_amount',
        'valid_from',
        'valid_to',
        'source_url',
    ];

    protected $casts = [
        'year' => 'integer',
        'from_amount' => 'decimal:2',
        'to_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
