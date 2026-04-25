<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ParseErrorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParseError extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function reprocessAtomRun(): BelongsTo
    {
        return $this->belongsTo(ReprocessAtomRun::class);
    }

    protected static function newFactory(): ParseErrorFactory
    {
        return ParseErrorFactory::new();
    }
}
