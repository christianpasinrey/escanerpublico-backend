<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BormeActItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'effective_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BormeEntry::class, 'borme_entry_id');
    }
}
