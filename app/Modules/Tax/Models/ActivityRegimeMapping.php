<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRegimeMapping extends Model
{
    protected $table = 'activity_regime_mappings';

    protected $fillable = [
        'activity_id',
        'eligible_regimes',
        'vat_rate_default',
        'irpf_retention_default',
        'notes',
    ];

    protected $casts = [
        'eligible_regimes' => 'array',
        'vat_rate_default' => 'integer',
        'irpf_retention_default' => 'decimal:2',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(EconomicActivity::class, 'activity_id');
    }
}
