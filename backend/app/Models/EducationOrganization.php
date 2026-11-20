<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationOrganization extends Model
{
    protected $fillable = ['type_id', 'area_id', 'name', 'short_name', 'bin', 'locality', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function legacySchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'legacy_school_id');
    }
}
