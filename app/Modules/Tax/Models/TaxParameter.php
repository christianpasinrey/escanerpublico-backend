<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;

class TaxParameter extends Model
{
    protected $table = 'tax_parameters';

    protected $fillable = [
        'year',
        'region_code',
        'key',
        'value',
        'source_url',
        'valid_from',
        'valid_to',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'value' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
