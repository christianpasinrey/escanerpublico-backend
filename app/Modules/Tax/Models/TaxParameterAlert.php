<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legislation\Models\LegislationNorm;

class TaxParameterAlert extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'tax_parameter_alerts';

    protected $fillable = [
        'source_legislation_norm_id',
        'suggested_action',
        'status',
        'matched_pattern',
        'notes',
    ];

    protected $casts = [
        'source_legislation_norm_id' => 'integer',
    ];

    public function legislationNorm(): BelongsTo
    {
        return $this->belongsTo(LegislationNorm::class, 'source_legislation_norm_id');
    }
}
