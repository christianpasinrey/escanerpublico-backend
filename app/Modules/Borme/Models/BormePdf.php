<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BormePdf extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'downloaded_at' => 'datetime',
        'parsed_at' => 'datetime',
    ];

    public function ingestRun(): BelongsTo
    {
        return $this->belongsTo(BormeIngestRun::class, 'borme_ingest_run_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BormeEntry::class);
    }

    public function unparsedActs(): HasMany
    {
        return $this->hasMany(BormeUnparsedAct::class);
    }
}
