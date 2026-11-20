<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherEmployment extends Model
{
    protected $fillable = ['school_user_id', 'user_id', 'school_id', 'position_title', 'status'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
