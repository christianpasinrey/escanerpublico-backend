<?php

namespace Modules\Subsidies\Models;

use Database\Factories\Modules\Subsidies\SubsidyCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contracts\Models\Organization;

class SubsidyCall extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reception_date' => 'date',
            'is_mrr' => 'boolean',
            'ingested_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(SubsidyGrant::class, 'call_id');
    }

    protected static function newFactory(): SubsidyCallFactory
    {
        return SubsidyCallFactory::new();
    }
}
