<?php

namespace Modules\Tax\Models;

use Database\Factories\Modules\Tax\TaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tax_type_id
 * @property int $year
 * @property string|null $region_code
 * @property string|null $rate
 * @property string|null $base_min
 * @property string|null $base_max
 * @property string|null $fixed_amount
 * @property array<string, mixed>|null $conditions
 * @property string|null $source_url
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 */
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

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

    /**
     * @return BelongsTo<TaxType, $this>
     */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class, 'tax_type_id');
    }

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }
}
