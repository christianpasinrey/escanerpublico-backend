<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    protected $guarded = ['id'];

    public const TYPE_LABELS = [
        'legal' => 'Documento administrativo',
        'technical' => 'Documento técnico',
        'additional' => 'Documento adicional',
        'general' => 'Documento general',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
