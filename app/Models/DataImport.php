<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataImport extends Model
{
    protected $guarded = [];

    protected $casts = ['errors' => 'array'];
}
