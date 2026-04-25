<?php

namespace Modules\Legislation\Models;

use Illuminate\Database\Eloquent\Model;

class LegislationIngestRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cursor_date' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
