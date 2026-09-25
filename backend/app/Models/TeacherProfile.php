<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    protected $fillable = [
        'user_id',
        'iin',
        'birth_date',
        'gender',
        'phone',
        'education_level',
        'educational_institution',
        'graduation_year',
        'diploma_number',
        'specialty',
        'total_experience_months',
        'teaching_experience_months',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'graduation_year' => 'integer',
            'total_experience_months' => 'integer',
            'teaching_experience_months' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}