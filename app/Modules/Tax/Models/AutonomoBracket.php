<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;

class AutonomoBracket extends Model
{
    protected $table = 'autonomo_brackets';

    protected $fillable = [
        'year',
        'bracket_number',
        'from_yield',
        'to_yield',
        'base_min',
        'base_max',
        'monthly_quota_min',
        'monthly_quota_max',
        'valid_from',
        'valid_to',
        'source_url',
    ];

    protected $casts = [
        'year' => 'integer',
        'bracket_number' => 'integer',
        'from_yield' => 'decimal:2',
        'to_yield' => 'decimal:2',
        'base_min' => 'decimal:2',
        'base_max' => 'decimal:2',
        'monthly_quota_min' => 'decimal:2',
        'monthly_quota_max' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
