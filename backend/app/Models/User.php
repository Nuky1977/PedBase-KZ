<?php

namespace App\Models;

use App\UserRole;
use Composer\InstalledVersions;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'role',
        'email',
        'password',
    ];

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function diplomas(): HasMany
    {
        return $this->hasMany(TeacherDiploma::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(TeacherCourse::class);
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(TeacherAttestation::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(TeacherAchievement::class);
    }

    public static function catalogPasswordDeliveryEnabled(): bool
    {
        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');

        if ($mailer === 'postmark-smtp') {
            $version = InstalledVersions::getVersion('symfony/mailer');
            // CVE-2026-133751 affects STARTTLS enforcement on older Mailer releases.
            $patched = $version !== null && (
                version_compare($version, '8.1.12', '>=')
                || (version_compare($version, '7.4.24', '>=') && version_compare($version, '8.0.0', '<'))
            );

            return $patched && filled(config('mail.mailers.postmark-smtp.username'))
                && filled(config('mail.mailers.postmark-smtp.password'));
        }

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true)
            || ($transport === 'array' && app()->environment('testing'));
    }

    public function sendPasswordResetNotification($token): void
    {
        if (self::catalogPasswordDeliveryEnabled()) {
            $this->notify(new ResetPassword($token));
        }
    }

    public function setPrimarySchool(School $school): void
    {
        $this->getConnection()->transaction(function () use ($school) {
            self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (config('pedbase.education_catalog_enabled')) {
                $catalogPrimary = $this->getConnection()->table('education_primary_workplaces as p')
                    ->join('education_organizations as o', 'o.id', '=', 'p.organization_id')
                    ->where('p.user_id', $this->getKey())->first(['o.legacy_school_id']);
                abort_if($catalogPrimary !== null && (int) $catalogPrimary->legacy_school_id !== (int) $school->getKey(), 422,
                    'Педагогтің каталогта басқа негізгі жұмыс орны бар.');
            }

            $this->schools()->newPivotQuery()->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            $this->schools()->syncWithoutDetaching([$school->getKey() => ['is_primary' => true]]);
        }, 3);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
