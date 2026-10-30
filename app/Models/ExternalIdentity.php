<?php

namespace App\Models;

use Database\Factories\ExternalIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalIdentity extends Model
{
    /** @use HasFactory<ExternalIdentityFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'issuer', 'subject'];

    protected static function booted(): void
    {
        static::creating(function (self $identity) {
            $identity->identity_key = hash('sha256', $identity->issuer."\0".$identity->subject);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
