<?php

namespace App\Models;

use App\Services\ProtectedIin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    protected $hidden = ['iin', 'iin_ciphertext', 'iin_lookup'];

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

    protected function iin(): Attribute
    {
        return Attribute::make(
            get: fn (#[\SensitiveParameter] ?string $legacy, array $attributes) => isset($attributes['iin_ciphertext'])
                ? app(ProtectedIin::class)->decrypt($attributes['iin_ciphertext'])
                : $legacy,
            set: static function (#[\SensitiveParameter] ?string $value): array {
                $protection = app(ProtectedIin::class);
                if ($value !== null) {
                    return ['iin' => null, ...$protection->protect($value)];
                }

                if (config('identity.key_store') !== 'environment') {
                    $protection->ensureWritable();
                }

                return [
                    'iin' => null,
                    'iin_ciphertext' => null,
                    'iin_lookup' => null,
                ];
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
