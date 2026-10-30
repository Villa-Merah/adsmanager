<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTarget extends Model
{
    protected $guarded = [];

    protected $casts = ['period_start' => 'date', 'target' => 'decimal:2'];
}
