<?php

namespace App\Http\Middleware;

use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user ||
            ! in_array($user->role, [
                UserRole::ADMIN,
                UserRole::TEACHER,
            ], true)
        ) {
            abort(403, 'Бұл бөлімге кіруге рұқсатыңыз жоқ.');
        }

        return $next($request);
    }
}