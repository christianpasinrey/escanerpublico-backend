<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BormeUnparsedAct extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function pdf(): BelongsTo
    {
        return $this->belongsTo(BormePdf::class, 'borme_pdf_id');
    }
}
