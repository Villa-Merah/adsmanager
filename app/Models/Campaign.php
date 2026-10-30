<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'budget' => 'decimal:2', 'revenue' => 'decimal:2'];

    protected $appends = ['ctr', 'cpl', 'roas'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(Closing::class);
    }

    public function getCtrAttribute(): float
    {
        return $this->impressions ? $this->link_clicks / $this->impressions : 0;
    }

    public function getCplAttribute(): float
    {
        return $this->leads_count ? (float) $this->budget / $this->leads_count : 0;
    }

    public function getRoasAttribute(): float
    {
        return (float) $this->budget > 0 ? (float) $this->revenue / (float) $this->budget : 0;
    }
}
