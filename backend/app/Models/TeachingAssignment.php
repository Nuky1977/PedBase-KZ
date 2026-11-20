<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingAssignment extends Model
{
    protected $fillable = ['subject_id', 'academic_year', 'weekly_hours'];

    protected function casts(): array
    {
        return ['academic_year' => 'integer', 'weekly_hours' => 'decimal:2'];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(TeacherEmployment::class, 'teacher_employment_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
