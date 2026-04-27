<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'people';

    protected $guarded = ['id'];
}
