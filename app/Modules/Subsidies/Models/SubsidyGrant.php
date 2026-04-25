<?php

namespace Modules\Subsidies\Models;

use Database\Factories\Modules\Subsidies\SubsidyGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;

class SubsidyGrant extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'grant_date' => 'date',
            'fecha_alta' => 'date',
            'amount' => 'decimal:2',
            'ayuda_equivalente' => 'decimal:2',
            'tiene_proyecto' => 'boolean',
            'ingested_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(SubsidyCall::class, 'call_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SubsidySnapshot::class);
    }

    protected static function newFactory(): SubsidyGrantFactory
    {
        return SubsidyGrantFactory::new();
    }
}
