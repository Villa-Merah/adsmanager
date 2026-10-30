<?php

namespace App\Models;

use Database\Factories\ExternalIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalIdentity extends Model
{
    /** @use HasFactory<ExternalIdentityFactory> */
    use HasFactory;
}
