<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AccountRemoval
{
    public function delete(User $user): void
    {
        $paths = $user->getConnection()->transaction(function () use ($user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($lockedUser->isAdmin(), 403);
            abort_if(
                (Schema::hasTable('teacher_achievements') && $lockedUser->achievements()->exists())
                || (Schema::hasTable('education_employments') && $user->getConnection()->table('education_employments')->where('user_id', $user->id)->exists()),
                409,
                'Еңбек тарихы немесе кәсіби жетістіктері бар аккаунтты жоюға болмайды.',
            );

            $paths = Schema::hasTable('teacher_courses')
                ? $lockedUser->courses()->whereNotNull('certificate_path')->pluck('certificate_path')->all()
                : [];

            $lockedUser->delete();

            return $paths;
        }, 3);

        if ($paths !== []) {
            Storage::disk('teacher-documents')->delete($paths);
        }
    }
}
