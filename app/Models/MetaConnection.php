<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['access_token'];

    protected $casts = ['last_synced_at' => 'datetime', 'access_token' => 'encrypted'];
}
