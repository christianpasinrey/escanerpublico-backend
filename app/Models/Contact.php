<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
