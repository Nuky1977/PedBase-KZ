<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class School extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'bin',
        'type',
        'locality',
        'is_active',
    ];
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('is_primary')
        ->withTimestamps();
}
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
    
}
