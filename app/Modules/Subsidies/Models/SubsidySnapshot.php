<?php

namespace Modules\Subsidies\Models;

use Database\Factories\Modules\Subsidies\SubsidySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubsidySnapshot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'fetched_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(SubsidyGrant::class, 'subsidy_grant_id');
    }

    protected static function newFactory(): SubsidySnapshotFactory
    {
        return SubsidySnapshotFactory::new();
    }
}
