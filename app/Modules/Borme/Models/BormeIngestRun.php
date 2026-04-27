<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BormeIngestRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'cursor_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function pdfs(): HasMany
    {
        return $this->hasMany(BormePdf::class);
    }
}
