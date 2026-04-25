<?php

namespace Modules\Subsidies\Models;

use Illuminate\Database\Eloquent\Model;

class SubsidyIngestRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
